<?php

namespace App\Http\Controllers\Antrol;

use App\Http\Controllers\Controller;
use App\Services\Antrol\WsbpjsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Halaman Antrian Pendaftaran Onsite BPJS, tab A (Cek Poli Fingerprint, item UAT 3)
 * dan tab B (Cek Peserta Fingerprint, item UAT 4).
 */
class PendaftaranFingerprintController extends Controller
{
    public function __construct(private WsbpjsService $ws) {}

    public function poli(): View
    {
        $cek = session('cek_poli_fp');

        return view('antrol.pendaftaran-poli-fp', [
            'cek' => $cek,
            'baris' => $cek ? $this->barisPoli($cek['hasil']) : [],
        ]);
    }

    /** Selalu memanggil BPJS (tanpa cache); hasil berhasil memperbarui cache poli fingerprint. */
    public function cekPoli(): RedirectResponse
    {
        return redirect()->route('antrol.pendaftaran.poli-fp')->with('cek_poli_fp', [
            'hasil' => $this->ws->perbaruiPoliFingerprint(),
            'waktu' => now()->format('d-m-Y H:i:s'),
        ]);
    }

    public function peserta(): View
    {
        $cek = session('cek_peserta_fp');

        return view('antrol.pendaftaran-peserta-fp', [
            'cek' => $cek,
            'ringkas' => $cek ? $this->ringkasPeserta($cek['hasil']) : null,
        ]);
    }

    public function cekPeserta(Request $r): RedirectResponse
    {
        $d = $r->validate([
            'jenis' => 'required|in:noka,nik',
            'nomor' => ['required', 'string', $r->input('jenis') === 'nik' ? 'digits:16' : 'digits:13'],
        ], [
            'nomor.digits' => $r->input('jenis') === 'nik' ? 'NIK harus 16 digit angka.' : 'Nomor kartu harus 13 digit angka.',
        ], ['nomor' => 'nomor identitas', 'jenis' => 'jenis identitas']);

        return redirect()->route('antrol.pendaftaran.peserta-fp')->withInput()->with('cek_peserta_fp', [
            'hasil' => $this->ws->referensiPasienFingerprint($d['jenis'], $d['nomor']),
            'jenis' => $d['jenis'],
            'nomor' => $d['nomor'],
            'waktu' => now()->format('d-m-Y H:i:s'),
        ]);
    }

    /**
     * Baris tabel poli fingerprint dari respons (daftar langsung atau dibungkus "list").
     *
     * @return list<array{kodepoli: string, namapoli: string, kodesubspesialis: string, namasubspesialis: string}>
     */
    private function barisPoli(array $hasil): array
    {
        $data = $hasil['response'] ?? [];
        if (is_array($data) && isset($data['list']) && is_array($data['list'])) {
            $data = $data['list'];
        }
        if (! is_array($data)) {
            return [];
        }

        $baris = [];
        foreach ($data as $b) {
            if (is_array($b)) {
                $baris[] = [
                    'kodepoli' => (string) ($b['kodepoli'] ?? $b['kdpoli'] ?? ''),
                    'namapoli' => (string) ($b['namapoli'] ?? $b['nmpoli'] ?? ''),
                    'kodesubspesialis' => (string) ($b['kodesubspesialis'] ?? $b['kdsubspesialis'] ?? ''),
                    'namasubspesialis' => (string) ($b['namasubspesialis'] ?? $b['nmsubspesialis'] ?? ''),
                ];
            }
        }

        return $baris;
    }

    /**
     * Ringkasan Referensi Pasien Fingerprint. Nama field mengikuti config antrol.respons.ref_pasien_fp.
     *
     * @return array{nik: ?string, nomorkartu: ?string, tgllahir: ?string, daftarfp: ?string, keterangan_daftarfp: ?string}
     */
    private function ringkasPeserta(array $hasil): array
    {
        $data = $hasil['response'] ?? null;
        if (is_array($data) && array_is_list($data)) {
            $data = $data[0] ?? null;
        }
        $data = is_array($data) ? $data : [];
        $field = (array) config('antrol.respons.ref_pasien_fp', []);
        $ambil = function (string $kunci) use ($data, $field): ?string {
            foreach ((array) ($field[$kunci] ?? [$kunci]) as $nama) {
                if (isset($data[$nama]) && ! is_array($data[$nama]) && (string) $data[$nama] !== '') {
                    return (string) $data[$nama];
                }
            }

            return null;
        };

        $tgllahir = $ambil('tgllahir');
        if ($tgllahir !== null && ctype_digit($tgllahir)) {
            $tgllahir = Carbon::createFromTimestampMs((int) $tgllahir, config('app.timezone'))->format('d-m-Y');
        }
        $daftarfp = $ambil('daftarfp');

        return [
            'nik' => $ambil('nik'),
            'nomorkartu' => $ambil('nomorkartu'),
            'tgllahir' => $tgllahir,
            'daftarfp' => $daftarfp,
            'keterangan_daftarfp' => $daftarfp !== null ? (config('antrol.respons.keterangan_daftarfp')[$daftarfp] ?? null) : null,
        ];
    }
}
