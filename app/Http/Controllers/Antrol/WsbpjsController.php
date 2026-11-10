<?php

namespace App\Http\Controllers\Antrol;

use App\Http\Controllers\Controller;
use App\Models\Antrol\AntreanFarmasi;
use App\Services\Antrol\AntreanFarmasiService;
use App\Services\Antrol\WsbpjsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Halaman untuk menjalankan & meng-capture service WSBPJS (5 item UAT ditambah service yang sudah lolos). */
class WsbpjsController extends Controller
{
    public function __construct(private WsbpjsService $ws) {}

    public function index()
    {
        return view('antrol.wsbpjs', ['hasil' => session('hasil'), 'judul' => session('judul')]);
    }

    public function poliFingerprint()
    {
        return $this->kembali('Referensi Poli Fingerprint', $this->ws->referensiPoliFingerprint());
    }

    public function pasienFingerprint(Request $r)
    {
        $data = $r->validate([
            'jenis' => 'required|in:nik,noka',
            'nomor' => 'required|string|max:20',
        ]);

        return $this->kembali('Referensi Pasien Fingerprint', $this->ws->referensiPasienFingerprint($data['jenis'], $data['nomor']));
    }

    /**
     * Tambah antrean farmasi (onsite).
     * Jika nomor antrean kosong, nomor diambil dari antrean farmasi lokal
     * dengan aturan yang sama seperti WSRS ambil antrean farmasi.
     */
    public function tambahFarmasi(Request $r, AntreanFarmasiService $svc)
    {
        $data = $r->validate([
            'kodebooking' => 'required|string|max:30',
            'jenisresep' => 'nullable|in:racikan,non racikan',
            'nomorantrean' => 'nullable|integer|min:1',
            'keterangan' => 'nullable|string|max:200',
        ]);

        $jenis = $data['jenisresep'] ?? null;
        $nomor = $data['nomorantrean'] ?? null;

        if (! $nomor) {
            $lokal = $svc->ambil($data['kodebooking'], (string) ($data['keterangan'] ?? ''));
            if (($lokal['metadata']['code'] ?? 0) !== 200) {
                return $this->kembali('Tambah Antrean Farmasi (validasi lokal gagal, tidak dikirim ke BPJS)', [
                    'lokal' => $lokal,
                ]);
            }
            $nomor = $lokal['response']['nomorantrean'];
            $jenis = strtolower($lokal['response']['jenisresep']);
        }

        if (! $jenis) {
            return back()->withErrors(['jenisresep' => 'Jenis resep wajib diisi jika nomor antrean diisi manual.'])->withInput();
        }

        $hasil = $this->ws->tambahAntreanFarmasi($data['kodebooking'], $jenis, (int) $nomor, (string) ($data['keterangan'] ?? ''));

        if (WsbpjsService::berhasil($hasil)) {
            AntreanFarmasi::where('kodebooking', $data['kodebooking'])
                ->whereDate('tanggal', now()->toDateString())
                ->update(['dikirim_bpjs_at' => now()]);
        }

        return $this->kembali('Tambah Antrean Farmasi', $hasil);
    }

    public function belumDilayani()
    {
        return $this->kembali('Antrean Belum Dilayani', $this->ws->antreanBelumDilayani());
    }

    public function belumDilayaniPerPoli(Request $r)
    {
        $d = $r->validate([
            'kodepoli' => 'required|string|max:10',
            'kodedokter' => 'required|string|max:15',
            'hari' => 'required|integer|between:1,8',
            'jampraktek' => 'required|string|max:20',
        ]);

        return $this->kembali(
            'Antrean Belum Dilayani Per Poli Per Dokter Per Hari Per Jam Praktek',
            $this->ws->antreanBelumDilayaniPerPoli($d['kodepoli'], $d['kodedokter'], (string) $d['hari'], $d['jampraktek'])
        );
    }

    public function antreanPerKodeBooking(Request $r): RedirectResponse
    {
        return $this->kembali('Antrean Per Kode Booking', $this->ws->antreanPerKodeBooking($this->kodeBooking($r)));
    }

    public function listWaktuTask(Request $r): RedirectResponse
    {
        return $this->kembali('List Waktu Task ID', $this->ws->listWaktuTask($this->kodeBooking($r)));
    }

    /** Kode booking dari form; dibatasi huruf, angka, - dan _ karena ikut disusun ke path URL. */
    private function kodeBooking(Request $r): string
    {
        return $r->validate(['kodebooking' => 'required|string|max:30|alpha_dash:ascii'], [], ['kodebooking' => 'kode booking'])['kodebooking'];
    }

    /** Simulasi pesan poli fingerprint di pendaftaran. */
    public function cekPendaftaran(Request $r)
    {
        $d = $r->validate([
            'kodepoli' => 'required|string|max:10',
            'jenis' => 'nullable|in:nik,noka',
            'nomor' => 'nullable|string|max:20',
        ]);

        $wajib = $this->ws->poliWajibFingerprint($d['kodepoli']);
        $pasien = (! empty($d['nomor']) && ! empty($d['jenis']))
            ? $this->ws->referensiPasienFingerprint($d['jenis'], $d['nomor'])
            : null;

        return view('antrol.wsbpjs', [
            'pendaftaran' => [
                'kodepoli' => strtoupper($d['kodepoli']),
                'wajib_fp' => $wajib,
                'pasien' => $pasien,
                'daftar_fp' => $this->ws->daftarKodePoliFingerprint(),
            ],
            'hasil' => null,
            'judul' => null,
        ]);
    }

    private function kembali(string $judul, array $hasil)
    {
        return redirect()->route('antrol.wsbpjs')->with(['hasil' => $hasil, 'judul' => $judul]);
    }
}
