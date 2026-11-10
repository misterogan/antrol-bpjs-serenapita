<?php

namespace App\Http\Controllers\Antrol;

use App\Http\Controllers\Controller;
use App\Models\Antrol\AntreanFarmasi;
use App\Models\Antrol\Resep;
use App\Repositories\AntreanRepository;
use App\Services\Antrol\Hasil;
use App\Services\Antrol\WsbpjsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * API internal untuk SIMRS vendor (/jkn/internal/*). Format jawaban sama dengan WSRS:
 * {"response": ..., "metadata": {"message": "...", "code": ...}}; sukses 200, gagal validasi 201.
 * Dokumentasi: docs/openapi.yaml (tag "API Internal untuk SIMRS Vendor").
 */
class InternalApiController extends Controller
{
    public function __construct(private AntreanRepository $repo, private WsbpjsService $ws) {}

    /** Dipanggil SIMRS vendor saat dokter menyelesaikan pelayanan poli (bersamaan dengan task ID 5). */
    public function simpanResep(Request $r): JsonResponse
    {
        $data = $r->json()->all();
        if (isset($data['jenisresep']) && is_string($data['jenisresep'])) {
            $data['jenisresep'] = $this->normalisasiJenisResep($data['jenisresep']);
        }

        $v = Validator::make($data, [
            'kodebooking' => 'required|string|max:30|alpha_dash:ascii',
            'jenisresep' => 'required|in:'.implode(',', Resep::JENIS),
            'norekammedis' => 'nullable|string|max:30',
            'namapasien' => 'nullable|string|max:100',
            'nomorkartu' => 'nullable|string|max:20',
            'kodepoli' => 'nullable|string|max:10',
            'tanggal' => 'nullable|date_format:Y-m-d',
        ], [
            'jenisresep.in' => 'jenisresep harus Racikan, Non Racikan, atau Tidak ada',
        ], ['kodebooking' => 'kodebooking', 'jenisresep' => 'jenisresep', 'tanggal' => 'tanggal']);
        if ($v->fails()) {
            return $this->jawab(Hasil::gagal($v->errors()->first()));
        }

        return $this->jawab(Hasil::ok($this->repo->simpanResep($v->validated(), Resep::SUMBER_VENDOR)));
    }

    /** Resep dibatalkan di SIMRS vendor: antrean farmasi hari ini yang masih menunggu ikut dibatalkan. */
    public function batalResep(string $kodebooking): JsonResponse
    {
        $resep = $this->repo->batalkanResep($kodebooking);
        if (! $resep) {
            return $this->jawab(Hasil::gagal('Resep dengan kode booking tersebut tidak ditemukan'));
        }

        $hariIni = now()->toDateString();
        $farmasi = $this->repo->cariAntreanFarmasi($kodebooking, $hariIni);
        if ($farmasi && $farmasi['status'] === AntreanFarmasi::MENUNGGU) {
            $this->repo->ubahStatusFarmasi($kodebooking, $hariIni, AntreanFarmasi::BATAL);
            $farmasi['status'] = AntreanFarmasi::BATAL;
        } elseif ($farmasi && $farmasi['status'] !== AntreanFarmasi::BATAL) {
            return $this->jawab(Hasil::gagal('Resep dibatalkan, tetapi antrean farmasi nomor '.$farmasi['nomorantrean'].' sudah berstatus '.$farmasi['status'].' sehingga tidak diubah'));
        }

        return $this->jawab(Hasil::ok([
            'resep' => $resep,
            'antrean_farmasi' => $farmasi ? ['nomorantrean' => (int) $farmasi['nomorantrean'], 'status' => $farmasi['status']] : null,
        ]));
    }

    public function poliFingerprint(): JsonResponse
    {
        $kode = $this->ws->daftarKodePoliFingerprint();
        if ($kode === [] && $this->ws->referensiPoliFingerprintGagal()) {
            return $this->jawab(Hasil::gagal('Referensi Poli Fingerprint dari BPJS gagal dibaca, coba lagi'));
        }

        return $this->jawab(Hasil::ok(['list' => $kode, 'jumlah' => count($kode)]));
    }

    /** Untuk pesan di layar pendaftaran SIMRS vendor. Referensi gagal dibaca = code 201, bukan false. */
    public function poliFingerprintSatu(string $kodepoli): JsonResponse
    {
        $kode = $this->ws->daftarKodePoliFingerprint();
        if ($kode === [] && $this->ws->referensiPoliFingerprintGagal()) {
            return $this->jawab(Hasil::gagal('Referensi Poli Fingerprint dari BPJS gagal dibaca, status fingerprint poli tidak dapat dipastikan'));
        }

        $wajib = in_array(strtoupper($kodepoli), $kode, true);

        return $this->jawab(Hasil::ok([
            'kodepoli' => strtoupper($kodepoli),
            'wajib_fingerprint' => $wajib,
            'pesan' => $wajib ? (string) config('antrol.pesan_fingerprint') : 'Poli ini tidak mewajibkan validasi sidik jari (fingerprint).',
        ]));
    }

    public function pesertaFingerprint(Request $r): JsonResponse
    {
        $v = Validator::make($r->query(), [
            'jenis' => 'required|in:noka,nik',
            'nomor' => ['required', 'string', $r->query('jenis') === 'nik' ? 'digits:16' : 'digits:13'],
        ], [
            'jenis.in' => 'jenis harus noka atau nik',
            'nomor.digits' => $r->query('jenis') === 'nik' ? 'NIK harus 16 digit angka' : 'Nomor kartu harus 13 digit angka',
        ], ['jenis' => 'jenis', 'nomor' => 'nomor']);
        if ($v->fails()) {
            return $this->jawab(Hasil::gagal($v->errors()->first()));
        }

        $hasil = $this->ws->referensiPasienFingerprint($r->query('jenis'), $r->query('nomor'));
        if (! is_array($hasil['metadata'] ?? null)) {
            return $this->jawab(Hasil::gagal('Referensi Pasien Fingerprint dari BPJS tidak dapat dibaca: '.WsbpjsService::pesan($hasil)));
        }

        // Jawaban BPJS diteruskan apa adanya (sudah didekripsi), termasuk code 0 "Peserta tidak terdaftar".
        return $this->jawab(Hasil::ok(['metadata_bpjs' => $hasil['metadata'], 'peserta' => $hasil['response']]));
    }

    public function antreanFarmasi(Request $r): JsonResponse
    {
        $v = Validator::make($r->query(), ['tanggal' => 'nullable|date_format:Y-m-d'], [], ['tanggal' => 'tanggal']);
        if ($v->fails()) {
            return $this->jawab(Hasil::gagal($v->errors()->first()));
        }
        $tanggal = $r->query('tanggal') ?: now()->toDateString();
        $stat = $this->repo->statistikFarmasi($tanggal);

        return $this->jawab(Hasil::ok([
            'tanggal' => $tanggal,
            'ringkasan' => ['totalantrean' => $stat['total'], 'sisaantrean' => $stat['sisa'], 'antreanpanggil' => $stat['dipanggil'], 'selesai' => $stat['selesai'], 'batal' => $stat['batal']],
            'list' => array_map(fn (array $f) => $this->bentuk($f), $this->repo->daftarAntreanFarmasi($tanggal)),
        ]));
    }

    public function antreanFarmasiSatu(Request $r, string $kodebooking): JsonResponse
    {
        $tanggal = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $r->query('tanggal')) ? $r->query('tanggal') : now()->toDateString();
        foreach ($this->repo->daftarAntreanFarmasi($tanggal) as $f) {
            if ($f['kodebooking'] === $kodebooking) {
                return $this->jawab(Hasil::ok($this->bentuk($f)));
            }
        }

        return $this->jawab(Hasil::gagal('Antrean farmasi untuk kode booking tersebut tidak ditemukan'));
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    private function bentuk(array $f): array
    {
        return [
            'nomorantrean' => $f['nomorantrean'],
            'namapasien' => $f['nama'],
            'norekammedis' => $f['norm'],
            'kodebooking' => $f['kodebooking'],
            'jenisresep' => $f['jenisresep'],
            'sumber' => $f['sumber'] === AntreanFarmasi::SUMBER_WSRS ? 'mobile_jkn' : 'onsite',
            'status' => $f['status'],
            'waktu' => ['ambil' => $f['diambil_at'], 'dipanggil' => $f['dipanggil_at'], 'selesai' => $f['selesai_at'], 'batal' => $f['batal_at']],
            'terkirim_bpjs' => $f['sumber'] === AntreanFarmasi::SUMBER_WSRS ? null : $f['dikirim_bpjs'],
        ];
    }

    private function normalisasiJenisResep(string $jenis): string
    {
        return match (strtolower(trim($jenis))) {
            'racikan' => 'Racikan',
            'non racikan', 'nonracikan', 'non-racikan' => 'Non Racikan',
            'tidak ada', 'tidakada' => 'Tidak ada',
            default => $jenis,
        };
    }

    private function jawab(array $hasil): JsonResponse
    {
        return response()->json($hasil, Hasil::kode($hasil));
    }
}
