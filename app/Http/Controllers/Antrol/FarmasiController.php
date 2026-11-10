<?php

namespace App\Http\Controllers\Antrol;

use App\Http\Controllers\Controller;
use App\Models\Antrol\AntreanFarmasi;
use App\Models\Antrol\Resep;
use App\Repositories\AntreanRepository;
use App\Services\Antrol\PelayananService;
use App\Services\Antrol\SumberDataTidakTersedia;
use App\Services\Antrol\VendorWsrsClient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Halaman kerja petugas farmasi: ringkasan & tabel antrean hari ini (diperbarui otomatis),
 * aksi Panggil / Selesai / Batalkan (task ID 6/7 ke BPJS), dan satu form "Buat antrean farmasi"
 * untuk pasien onsite (simpan resep, aturan antrean farmasi, Tambah Antrean Farmasi).
 */
class FarmasiController extends Controller
{
    public function __construct(private AntreanRepository $repo, private PelayananService $layanan) {}

    public function index(): View
    {
        return view('antrol.farmasi', $this->dataTabel() + $this->dataSiap() + [
            'hasilAmbil' => session('farmasi_ambil'),
        ]);
    }

    /** Data tabel, ringkasan, dan daftar siap dibuatkan antrean untuk pembaruan otomatis (setiap 10 detik). */
    public function data(): JsonResponse
    {
        $data = $this->dataTabel() + $this->dataSiap();

        return response()->json([
            'ringkasan' => view('antrol.partials.farmasi-ringkasan', $data)->render(),
            'baris' => view('antrol.partials.farmasi-baris', $data)->render(),
            'siap' => view('antrol.partials.farmasi-siap', $data)->render(),
            'statistik' => $data['statistik'],
            'diperbarui' => now()->format('H:i:s'),
        ]);
    }

    /**
     * Data resep satu kode booking untuk mengisi form otomatis. Mode lokal: jika belum ada resep,
     * dipakai data uji antrean poli (tanpa memanggil BPJS).
     */
    public function cariResep(string $kodebooking): JsonResponse
    {
        $resep = $this->repo->cariResep($kodebooking);
        if ($resep && ! $resep['dibatalkan']) {
            return response()->json([
                'ada' => true,
                'namapasien' => $resep['namapasien'],
                'norekammedis' => $resep['norekammedis'],
                'jenisresep' => in_array($resep['jenisresep'], ['Racikan', 'Non Racikan'], true) ? $resep['jenisresep'] : null,
                'sumber' => $resep['sumber'],
                'label' => $resep['sumber'] === Resep::SUMBER_VENDOR ? 'data dari SIMRS' : 'data resep sebelumnya',
            ]);
        }

        if (config('antrol.sumber_data') !== 'bpjs') {
            try {
                $poli = $this->repo->cariAntreanPoli($kodebooking);
            } catch (SumberDataTidakTersedia) {
                $poli = null;
            }
            if ($poli) {
                return response()->json([
                    'ada' => true,
                    'namapasien' => $poli['nama'] ?? null,
                    'norekammedis' => $poli['norm'] ?? null,
                    'jenisresep' => in_array($poli['jenisresep'] ?? null, ['Racikan', 'Non Racikan'], true) ? $poli['jenisresep'] : null,
                    'sumber' => 'data_uji',
                    'label' => 'data dari data uji poli',
                ]);
            }
        }

        return response()->json(['ada' => false]);
    }

    /**
     * Form "Buat antrean farmasi": simpan/perbarui resep (data vendor tidak ditimpa kecuali diubah petugas),
     * jalankan aturan antrean farmasi yang sama dengan WSRS, beri nomor, lalu kirim Tambah Antrean Farmasi.
     * Jika ditolak, data resep tetap tersimpan supaya bisa dicoba lagi.
     */
    public function buat(Request $r): JsonResponse|RedirectResponse
    {
        $v = Validator::make($r->all(), [
            'kodebooking' => 'required|string|max:30|alpha_dash:ascii',
            'namapasien' => 'nullable|string|max:100',
            'norekammedis' => 'nullable|string|max:30',
            'jenisresep' => 'required|in:Racikan,Non Racikan',
            'keterangan' => 'required|string|max:200',
        ], [], ['kodebooking' => 'kode booking poli', 'namapasien' => 'nama pasien', 'norekammedis' => 'No. RM', 'jenisresep' => 'jenis resep', 'keterangan' => 'keterangan']);
        if ($v->fails()) {
            return $this->tolak($r, $v->errors()->first());
        }
        $d = $v->validated();
        $kodebooking = trim($d['kodebooking']);

        $this->simpanResepDariForm($kodebooking, $d);

        $hasil = $this->layanan->ambilFarmasi($kodebooking, trim($d['keterangan']));
        if (! $hasil['ok']) {
            return $this->tolak($r, $hasil['pesan']);
        }

        $tiket = [
            'kodebooking' => $kodebooking,
            'nomorantrean' => $hasil['data']['nomorantrean'],
            'jenisresep' => $hasil['data']['jenisresep'],
            'terkirim' => $hasil['peringatan'] === null,
            'bpjs' => $hasil['bpjs'][0] ?? null,
        ];
        $pesan = 'Antrean farmasi nomor '.$tiket['nomorantrean'].' dibuat.';

        if ($r->expectsJson()) {
            return response()->json([
                'ok' => true,
                'pesan' => $pesan,
                'peringatan' => $hasil['peringatan'],
                'kodebooking' => $kodebooking,
                'nomorantrean' => $tiket['nomorantrean'],
                'terkirim' => $tiket['terkirim'],
                'tiket' => view('antrol.partials.farmasi-tiket', ['hasilAmbil' => $tiket])->render(),
            ]);
        }

        return redirect()->route('antrol.farmasi')->with('pesan', $pesan)->with('peringatan', $hasil['peringatan'])->with('farmasi_ambil', $tiket);
    }

    /** Ambil antrean dari kode booking saja (endpoint lama; halaman memakai form "Buat antrean farmasi"). */
    public function ambil(Request $r): RedirectResponse
    {
        $d = $r->validate([
            'kodebooking' => 'required|string|max:30',
            'keterangan' => 'nullable|string|max:200',
        ], [], ['kodebooking' => 'kode booking']);
        $kodebooking = trim($d['kodebooking']);

        $hasil = $this->layanan->ambilFarmasi($kodebooking, (string) ($d['keterangan'] ?? ''));
        if (! $hasil['ok']) {
            return redirect()->route('antrol.farmasi')->withErrors(['farmasi' => $hasil['pesan']])->withInput();
        }

        return redirect()->route('antrol.farmasi')
            ->with('pesan', $hasil['pesan'])
            ->with('peringatan', $hasil['peringatan'])
            ->with('farmasi_ambil', [
                'kodebooking' => $kodebooking,
                'nomorantrean' => $hasil['data']['nomorantrean'],
                'jenisresep' => $hasil['data']['jenisresep'],
                'terkirim' => $hasil['peringatan'] === null,
                'bpjs' => $hasil['bpjs'][0] ?? null,
            ]);
    }

    public function aksi(string $kodebooking, string $aksi): RedirectResponse
    {
        return $this->kembali($this->layanan->aksiFarmasi($kodebooking, $aksi));
    }

    /** Status Antrean Farmasi dari SIMRS vendor (ringkas: total, sisa, dipanggil) sebagai flash message. */
    public function cekStatus(string $kodebooking, VendorWsrsClient $vendor): RedirectResponse
    {
        $hasil = $vendor->statusAntreanFarmasi($kodebooking);
        $balik = redirect()->route('antrol.farmasi');
        if (! $hasil['ok']) {
            return $balik->withErrors(['farmasi' => 'Cek status '.$kodebooking.' ditolak SIMRS vendor: '.$hasil['pesan']]);
        }

        $r = (array) $hasil['response'];

        return $balik->with('pesan', sprintf(
            'Status antrean farmasi %s (SIMRS vendor): sisa %s, dipanggil %s, total %s%s.',
            $kodebooking,
            $r['sisaantrean'] ?? '-',
            $r['antreanpanggil'] ?? '-',
            $r['totalantrean'] ?? '-',
            ! empty($r['jenisresep']) ? ', '.$r['jenisresep'] : ''
        ));
    }

    public function kirimUlang(string $kodebooking): RedirectResponse
    {
        return $this->kembali($this->layanan->kirimUlangFarmasi($kodebooking));
    }

    public function kirimTask(string $kodebooking, string $taskid): RedirectResponse
    {
        return $this->kembali($this->layanan->kirimUlangTaskFarmasi($kodebooking, (int) $taskid));
    }

    /**
     * Resep belum ada: disimpan sebagai input manual. Resep sudah ada: kolom yang dikosongkan petugas tetap
     * memakai data lama; data vendor hanya ditimpa jika petugas benar-benar mengubah isinya.
     *
     * @param  array<string, mixed>  $d
     */
    private function simpanResepDariForm(string $kodebooking, array $d): void
    {
        $lama = $this->repo->cariResep($kodebooking);
        $baru = [
            'kodebooking' => $kodebooking,
            'jenisresep' => $d['jenisresep'],
            'namapasien' => trim((string) ($d['namapasien'] ?? '')) ?: ($lama['namapasien'] ?? null),
            'norekammedis' => trim((string) ($d['norekammedis'] ?? '')) ?: ($lama['norekammedis'] ?? null),
            'nomorkartu' => $lama['nomorkartu'] ?? null,
            'kodepoli' => $lama['kodepoli'] ?? null,
            'tanggal' => $lama['tanggal'] ?? now()->toDateString(),
        ];

        if ($lama) {
            $berubah = mb_strtoupper((string) $baru['namapasien']) !== mb_strtoupper((string) $lama['namapasien'])
                || (string) $baru['norekammedis'] !== (string) $lama['norekammedis']
                || $baru['jenisresep'] !== $lama['jenisresep'];
            if (! $berubah) {
                return;
            }
        }

        $this->repo->simpanResep($baru, Resep::SUMBER_MANUAL);
    }

    private function tolak(Request $r, string $pesan): JsonResponse|RedirectResponse
    {
        if ($r->expectsJson()) {
            return response()->json(['ok' => false, 'pesan' => $pesan], 422);
        }

        return redirect()->route('antrol.farmasi')->withErrors(['farmasi' => $pesan])->withInput()->with('buka_form', true);
    }

    /** @return array<string, mixed> */
    private function dataTabel(): array
    {
        $tanggal = now()->toDateString();
        $antrean = $this->repo->daftarAntreanFarmasi($tanggal);
        $onsite = array_filter($antrean, fn (array $f) => $f['sumber'] !== AntreanFarmasi::SUMBER_WSRS && $f['status'] !== AntreanFarmasi::BATAL);

        return [
            // Sumber yang sama dengan AntreanFarmasiService::status() (WSRS Status Antrean Farmasi).
            'statistik' => $this->repo->statistikFarmasi($tanggal),
            'antrean' => $antrean,
            // Antrean dari Mobile JKN (WSRS) sudah diketahui BPJS, jadi tidak dihitung.
            'perluKirim' => count($onsite),
            'terkirim' => count(array_filter($onsite, fn (array $f) => $f['dikirim_bpjs'])),
            'dariMobileJkn' => count(array_filter($antrean, fn (array $f) => $f['sumber'] === AntreanFarmasi::SUMBER_WSRS)),
            'kirimTask' => (bool) config('antrol.kirim_task_farmasi'),
            'cekStatusVendor' => config('antrol.wsrs.provider', 'vendor') === 'vendor',
        ];
    }

    /**
     * Pasien yang siap dibuatkan antrean: mode bpjs = resep dari SIMRS vendor yang belum mengambil antrean;
     * mode lokal = data uji poli yang sudah selesai dengan resep.
     *
     * @return array{siap: list<array<string, mixed>>, modeBpjs: bool}
     */
    private function dataSiap(): array
    {
        $modeBpjs = config('antrol.sumber_data') === 'bpjs';
        $siap = $this->repo->daftarSiapAmbilFarmasi(now()->toDateString());
        if ($modeBpjs) {
            $siap = array_values(array_filter($siap, fn (array $s) => ($s['sumber_resep'] ?? null) === Resep::SUMBER_VENDOR));
        }

        return ['siap' => $siap, 'modeBpjs' => $modeBpjs];
    }

    private function kembali(array $hasil): RedirectResponse
    {
        $balik = redirect()->route('antrol.farmasi');
        if (! $hasil['ok']) {
            $balik->withErrors(['farmasi' => $hasil['pesan']])->withInput();
        } else {
            $balik->with('pesan', $hasil['pesan']);
        }

        return $balik->with(['peringatan' => $hasil['peringatan'], 'bpjs' => $hasil['bpjs'] ?: null]);
    }
}
