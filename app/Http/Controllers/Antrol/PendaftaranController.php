<?php

namespace App\Http\Controllers\Antrol;

use App\Http\Controllers\Controller;
use App\Repositories\AntreanRepository;
use App\Services\Antrol\PelayananService;
use App\Services\Antrol\WsbpjsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Layar pendaftaran rawat jalan: pilih pasien, cek fingerprint, pilih poli & jadwal, simpan. */
class PendaftaranController extends Controller
{
    public function __construct(
        private AntreanRepository $repo,
        private PelayananService $layanan,
        private WsbpjsService $ws,
    ) {}

    public function index(Request $r): View
    {
        return view('antrol.pendaftaran', $this->dataHalaman($r));
    }

    /** Tambah pasien baru (data uji tahap 1), lalu langsung dipilih. */
    public function simpanPasien(Request $r): RedirectResponse
    {
        $d = $r->validate([
            'norm' => 'required|string|max:20|unique:antrol_pasien_uji,norm',
            'nama' => 'required|string|max:100',
            'nomorkartu' => 'nullable|digits:13',
            'nik' => 'nullable|digits:16',
            'nohp' => 'nullable|string|max:20',
            'tanggal_lahir' => 'nullable|date|before_or_equal:today',
        ], [], $this->namaAtribut());

        $pasien = $this->repo->simpanPasien($d);

        return redirect()
            ->route('antrol.pendaftaran', ['norm' => $pasien['norm'], 'tanggal' => $r->input('tanggal')])
            ->with('pesan', 'Pasien '.$pasien['nama'].' tersimpan dengan No. RM '.$pasien['norm'].'.');
    }

    /** Langkah konfirmasi: validasi dan tampilkan pesan fingerprint sebelum disimpan. */
    public function periksa(Request $r): View|RedirectResponse
    {
        $d = $this->validasiPendaftaran($r);
        $cek = $this->layanan->periksaPendaftaran($d);

        if (! $cek['ok']) {
            return back()->withErrors(['pendaftaran' => $cek['pesan']])->withInput();
        }

        return view('antrol.pendaftaran', $this->dataHalaman($r, [
            'input' => $d,
            'pasien' => $cek['pasien'],
            'jadwal' => $cek['jadwal'],
            'wajib_fp' => $cek['wajib_fp'],
        ]));
    }

    public function simpan(Request $r): RedirectResponse
    {
        $d = $this->validasiPendaftaran($r);
        $hasil = $this->layanan->daftar($d, $r->boolean('konfirmasi_fp'));

        if (! $hasil['ok']) {
            return redirect()
                ->route('antrol.pendaftaran', ['norm' => $d['norm'], 'tanggal' => $d['tanggal']])
                ->withErrors(['pendaftaran' => $hasil['pesan']]);
        }

        return redirect()
            ->route('antrol.pendaftaran', ['tanggal' => $d['tanggal']])
            ->with([
                'terdaftar' => $hasil['data'],
                'pesan' => $hasil['pesan'],
                'peringatan' => $hasil['peringatan'],
                'bpjs' => $hasil['bpjs'] ?: null,
            ]);
    }

    /** @return array<string, mixed> */
    private function dataHalaman(Request $r, ?array $konfirmasi = null): array
    {
        $tanggal = $this->tanggal($r);
        $norm = (string) ($konfirmasi['pasien']['norm'] ?? $r->query('norm', ''));
        $kata = trim((string) $r->query('q', ''));

        $pasien = $norm !== '' ? $this->repo->pasienByNorm($norm) : null;

        return [
            'tanggal' => $tanggal,
            'kata' => $kata,
            'hasilCari' => $kata !== '' ? $this->repo->cariPasien($kata) : [],
            'pasien' => $pasien,
            'fpPasien' => $pasien ? $this->fingerprintPasien($pasien, $r->boolean('cek')) : null,
            'jadwal' => $this->repo->daftarJadwal($tanggal),
            'sumberJadwal' => $this->repo->ringkasanSumberJadwal(),
            'poliFp' => $this->ws->daftarKodePoliFingerprint(),
            'poliFpGagal' => $this->ws->referensiPoliFingerprintGagal(),
            'konfirmasi' => $konfirmasi,
            'daftar' => $this->repo->daftarAntreanPoli($tanggal),
            'terdaftar' => session('terdaftar'),
            'kunjungan' => PelayananService::JENIS_KUNJUNGAN,
        ];
    }

    /**
     * Referensi Pasien Fingerprint, disimpan di sesi per pasien supaya tidak dipanggil
     * ulang setiap halaman dimuat. Parameter cek=1 memaksa cek ulang ke BPJS.
     */
    private function fingerprintPasien(array $pasien, bool $paksa): ?array
    {
        $kunci = 'antrol.fp_pasien.'.$pasien['norm'];
        if (! $paksa && session()->has($kunci)) {
            return session($kunci);
        }

        $fp = $this->layanan->cekFingerprintPasien($pasien);
        if ($fp) {
            $fp['waktu'] = now()->format('H:i');
            session([$kunci => $fp]);
        }

        return $fp;
    }

    private function tanggal(Request $r): string
    {
        $t = (string) $r->input('tanggal', '');

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) && $t >= now()->toDateString() ? $t : now()->toDateString();
    }

    /** @return array{norm: string, jadwal_id: string, tanggal: string, jenispasien: string, jeniskunjungan: ?int, nomorreferensi: ?string} */
    private function validasiPendaftaran(Request $r): array
    {
        $d = $r->validate([
            'norm' => 'required|string|max:20',
            'jadwal_id' => 'required|string|max:50',
            'tanggal' => 'required|date_format:Y-m-d|after_or_equal:today',
            'jenispasien' => 'required|in:JKN,NON JKN',
            'jeniskunjungan' => 'nullable|integer|in:'.implode(',', array_keys(PelayananService::JENIS_KUNJUNGAN)),
            'nomorreferensi' => 'nullable|string|max:30',
        ], [
            'jadwal_id.required' => 'Pilih poli, dokter, dan jam praktek.',
            'norm.required' => 'Pilih pasien terlebih dahulu.',
            'tanggal.after_or_equal' => 'Tanggal periksa tidak boleh sebelum hari ini.',
        ], $this->namaAtribut());

        $d['jeniskunjungan'] = isset($d['jeniskunjungan']) ? (int) $d['jeniskunjungan'] : null;
        $d['nomorreferensi'] = $d['nomorreferensi'] ?? null;

        return $d;
    }

    /** @return array<string, string> */
    private function namaAtribut(): array
    {
        return [
            'norm' => 'No. RM',
            'nama' => 'nama',
            'nomorkartu' => 'nomor kartu',
            'nik' => 'NIK',
            'nohp' => 'nomor HP',
            'tanggal_lahir' => 'tanggal lahir',
            'tanggal' => 'tanggal periksa',
            'jenispasien' => 'jenis pasien',
            'jeniskunjungan' => 'jenis kunjungan',
            'nomorreferensi' => 'nomor rujukan/referensi',
        ];
    }
}
