<?php

namespace App\Repositories;

use App\Models\Antrol\AntreanFarmasi;
use App\Models\Antrol\Resep;
use App\Services\Antrol\SumberDataTidakTersedia;
use App\Services\Antrol\WsbpjsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Sumber data 'bpjs': antrean farmasi diperiksa ke BPJS (Antrean Per Kode Booking dan List Waktu Task ID)
 * dan data resep yang dikirim SIMRS vendor (tabel antrol_resep). Modul tidak terhubung ke database vendor.
 *
 * Bagian lain (pendaftaran simulasi, poli simulasi, jadwal) tetap memakai tabel lokal.
 */
class BpjsAntreanRepository extends LocalAntreanRepository
{
    public const PESAN_TIDAK_TERSEDIA = 'Data antrean BPJS tidak dapat diperiksa, silakan coba lagi';

    public function __construct(private WsbpjsService $ws) {}

    public function cariAntreanPoli(string $kodebooking): ?array
    {
        $bpjs = $this->periksaBpjs($kodebooking);
        if ($bpjs === null) {
            return null;
        }

        $resep = Resep::where('kodebooking', $kodebooking)->first();

        return [
            'kodebooking' => $kodebooking,
            'tanggal' => $bpjs['tanggal'],
            'batal' => $bpjs['batal'],
            'checkin' => true,
            'taskid5' => $bpjs['taskid5'],
            // Resep dibatalkan vendor = tidak ada resep; resep belum dikirim = pesan tersendiri.
            'jenisresep' => $resep && $resep->dibatalkan_at === null ? $resep->jenisresep : null,
            'resep_belum_ada' => $resep === null,
            'nama' => $resep?->namapasien,
            'norm' => $resep?->norekammedis ?: $bpjs['norekammedis'],
        ];
    }

    public function daftarSiapAmbilFarmasi(string $tanggal): array
    {
        $sudah = AntreanFarmasi::whereDate('tanggal', $tanggal)->pluck('kodebooking');

        return Resep::whereDate('tanggal', $tanggal)
            ->whereNull('dibatalkan_at')
            ->whereIn('jenisresep', ['Racikan', 'Non Racikan'])
            ->whereNotIn('kodebooking', $sudah)
            ->orderBy('updated_at')
            ->get()
            ->map(fn (Resep $r) => [
                'kodebooking' => $r->kodebooking,
                'nama' => $r->namapasien,
                'norm' => $r->norekammedis,
                'jenisresep' => $r->jenisresep,
                'namapoli' => $r->kodepoli,
                'selesai_at' => $r->updated_at?->format('H:i'),
                'sumber_resep' => $r->sumber,
            ])
            ->all();
    }

    /**
     * Data antrean poli di BPJS: null jika tidak ditemukan. Hanya hasil yang lolos (hari ini, tidak batal,
     * sudah task ID 5) yang di-cache, supaya pasien yang baru selesai poli tidak tertahan cache lama.
     *
     * @return array{tanggal: string, batal: bool, taskid5: bool, norekammedis: ?string}|null
     *
     * @throws SumberDataTidakTersedia
     */
    private function periksaBpjs(string $kodebooking): ?array
    {
        $kunci = 'antrol.cek_bpjs.'.sha1($kodebooking);
        $tersimpan = Cache::get($kunci);
        if (is_array($tersimpan)) {
            return $tersimpan;
        }

        $hasil = $this->ws->antreanPerKodeBooking($kodebooking);
        if (! WsbpjsService::berhasil($hasil)) {
            if ($this->tidakDitemukan($hasil, 'antrean_kodebooking')) {
                return null;
            }
            throw new SumberDataTidakTersedia(self::PESAN_TIDAK_TERSEDIA);
        }

        $baris = $this->barisPertama($hasil);
        if ($baris === null) {
            return null;
        }

        $field = (array) config('antrol.respons.antrean_kodebooking', []);
        $tanggal = $this->tanggal($this->ambil($baris, $field['tanggal'] ?? ['tanggal']));
        $status = mb_strtolower((string) $this->ambil($baris, $field['status'] ?? ['status']));
        $batal = str_contains($status, 'batal');

        $data = [
            'tanggal' => $tanggal,
            'batal' => $batal,
            'taskid5' => ! $batal && $tanggal === now()->toDateString() && $this->sudahTask5($kodebooking),
            'norekammedis' => $this->ambil($baris, $field['norekammedis'] ?? ['norekammedis']),
        ];

        if (! $data['batal'] && $data['tanggal'] === now()->toDateString() && $data['taskid5']) {
            Cache::put($kunci, $data, now()->addMinutes(max(1, (int) config('antrol.cek_bpjs_cache_menit', 2))));
        }

        return $data;
    }

    /** @throws SumberDataTidakTersedia */
    private function sudahTask5(string $kodebooking): bool
    {
        $hasil = $this->ws->listWaktuTask($kodebooking);
        if (! WsbpjsService::berhasil($hasil)) {
            if ($this->tidakDitemukan($hasil, 'list_task')) {
                return false;
            }
            throw new SumberDataTidakTersedia(self::PESAN_TIDAK_TERSEDIA);
        }

        $field = (array) config('antrol.respons.list_task.taskid', ['taskid']);
        foreach ($this->semuaBaris($hasil) as $baris) {
            if ((int) $this->ambil($baris, $field) === 5) {
                return true;
            }
        }

        return false;
    }

    /** Jawaban "data tidak ditemukan" sesuai config antrol.respons.{service}.tidak_ditemukan (code DAN pesan). */
    private function tidakDitemukan(array $hasil, string $service): bool
    {
        $aturan = (array) config("antrol.respons.{$service}.tidak_ditemukan", []);
        $kode = $hasil['metadata']['code'] ?? null;
        if ($kode === null || ! in_array((int) $kode, array_map('intval', (array) ($aturan['code'] ?? [])), true)) {
            return false;
        }

        $pesan = mb_strtolower((string) ($hasil['metadata']['message'] ?? ''));
        foreach ((array) ($aturan['pesan'] ?? []) as $pola) {
            if ($pola !== '' && str_contains($pesan, mb_strtolower((string) $pola))) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array<string, mixed>> */
    private function semuaBaris(array $hasil): array
    {
        $data = $hasil['response'] ?? [];
        if (is_array($data) && isset($data['list']) && is_array($data['list'])) {
            $data = $data['list'];
        }
        if (! is_array($data)) {
            return [];
        }

        return array_is_list($data) ? array_values(array_filter($data, 'is_array')) : [$data];
    }

    /** @return array<string, mixed>|null */
    private function barisPertama(array $hasil): ?array
    {
        return $this->semuaBaris($hasil)[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $baris
     * @param  list<string>  $nama
     */
    private function ambil(array $baris, array $nama): ?string
    {
        foreach ($nama as $n) {
            if (isset($baris[$n]) && ! is_array($baris[$n]) && (string) $baris[$n] !== '') {
                return (string) $baris[$n];
            }
        }

        return null;
    }

    /** Tanggal antrean BPJS ke Y-m-d (Asia/Jakarta); format tak dikenal dianggap jawaban tidak dikenali. */
    private function tanggal(?string $nilai): string
    {
        try {
            if ($nilai !== null && ctype_digit($nilai) && strlen($nilai) >= 12) {
                return Carbon::createFromTimestampMs((int) $nilai, config('app.timezone'))->toDateString();
            }
            if ($nilai !== null) {
                return Carbon::parse($nilai, config('app.timezone'))->toDateString();
            }
        } catch (Throwable) {
            // jatuh ke pengecualian di bawah
        }

        throw new SumberDataTidakTersedia(self::PESAN_TIDAK_TERSEDIA);
    }
}
