<?php

namespace App\Services\Antrol;

use Illuminate\Support\Facades\Cache;

/**
 * Pembungkus 5 service WSBPJS yang belum lolos UAT,
 * ditambah Tambah Antrean & Update Waktu untuk mode simulasi.
 */
class WsbpjsService
{
    /**
     * @param  array<string, string>  $paths  config('antrol.paths')
     * @param  array<string, mixed>  $body  config('antrol.body'): daftar field body per service
     */
    public function __construct(private AntrolClient $client, private array $paths, private array $body = []) {}

    /**
     * Tambah Antrean (mode simulasi). Field dikirim sesuai config antrol.body.tambah_antrean.
     *
     * @param  array<string, mixed>  $data
     */
    public function tambahAntrean(array $data): array
    {
        return $this->client->post($this->paths['tambah_antrean'], $this->susunBody('tambah_antrean', $data));
    }

    /**
     * Update Waktu antrean (mode simulasi). Jenis resep hanya disertakan pada task ID 5.
     *
     * @param  int  $waktu  timestamp dalam milidetik
     * @param  string|null  $jenisresep  'Racikan' | 'Non Racikan' | 'Tidak ada'
     */
    public function updateWaktu(string $kodebooking, int $taskid, int $waktu, ?string $jenisresep = null): array
    {
        $data = ['kodebooking' => $kodebooking, 'taskid' => $taskid, 'waktu' => $waktu];
        if ($taskid === 5 && $jenisresep !== null) {
            $data['jenisresep'] = $this->body['jenisresep_update_waktu'][$jenisresep] ?? $jenisresep;
        }

        return $this->client->post($this->paths['update_waktu'], $this->susunBody('update_waktu', $data));
    }

    /** True jika metadata.code respons BPJS termasuk config antrol.kode_sukses (default 200 dan 1). */
    public static function berhasil(?array $hasil): bool
    {
        $kode = $hasil['metadata']['code'] ?? null;
        if ($kode === null || $kode === '') {
            return false;
        }

        return in_array((int) $kode, array_map('intval', (array) config('antrol.kode_sukses', [200])), true);
    }

    /** Pesan metadata BPJS, atau galat koneksi. */
    public static function pesan(array $hasil): string
    {
        return (string) ($hasil['metadata']['message'] ?? $hasil['error'] ?? 'Tidak ada respons dari BPJS');
    }

    public function referensiPoliFingerprint(): array
    {
        return $this->client->get($this->paths['ref_poli_fp']);
    }

    /** Antrean Per Kode Booking (halaman Uji service). */
    public function antreanPerKodeBooking(string $kodebooking): array
    {
        return $this->client->get($this->isi($this->paths['antrean_kodebooking'], ['kodebooking' => $kodebooking]));
    }

    /** List Waktu Task ID (halaman Uji service). */
    public function listWaktuTask(string $kodebooking): array
    {
        return $this->client->post($this->paths['list_task'], ['kodebooking' => $kodebooking]);
    }

    /** Referensi Poli (untuk sinkron jadwal). */
    public function referensiPoli(): array
    {
        return $this->client->get($this->paths['ref_poli']);
    }

    /** Referensi Jadwal Dokter satu poli pada tanggal Y-m-d (untuk sinkron jadwal). */
    public function referensiJadwalDokter(string $kodepoli, string $tanggal): array
    {
        return $this->client->get($this->isi($this->paths['ref_jadwal_dokter'], [
            'kodepoli' => $kodepoli,
            'tanggal' => $tanggal,
        ]));
    }

    /** $jenis: 'nik' atau 'noka' */
    public function referensiPasienFingerprint(string $jenis, string $nomor): array
    {
        return $this->client->get($this->isi($this->paths['ref_pasien_fp'], [
            'jenis' => $jenis,
            'nomor' => $nomor,
        ]));
    }

    /**
     * Tambah Antrean Farmasi. Keterangan kosong diganti config antrol.keterangan_farmasi_default,
     * karena BPJS menolak keterangan kosong. Dipakai halaman Farmasi, Kirim ulang, dan Uji service.
     */
    public function tambahAntreanFarmasi(string $kodebooking, string $jenisresep, int $nomorantrean, string $keterangan = ''): array
    {
        return $this->client->post($this->paths['tambah_farmasi'], [
            'kodebooking' => $kodebooking,
            'jenisresep' => $jenisresep,
            'nomorantrean' => $nomorantrean,
            'keterangan' => self::keteranganFarmasi($keterangan),
        ]);
    }

    /** Keterangan yang dikirim di Tambah Antrean Farmasi: isian pengguna, atau nilai default jika kosong. */
    public static function keteranganFarmasi(?string $keterangan): string
    {
        $keterangan = trim((string) $keterangan);

        return $keterangan !== '' ? $keterangan : (string) config('antrol.keterangan_farmasi_default');
    }

    public function antreanBelumDilayani(): array
    {
        return $this->client->get($this->paths['belum_dilayani']);
    }

    public function antreanBelumDilayaniPerPoli(string $kodepoli, string $kodedokter, string $hari, string $jampraktek): array
    {
        return $this->client->get($this->isi($this->paths['belum_dilayani_poli'], [
            'kodepoli' => $kodepoli,
            'kodedokter' => $kodedokter,
            'hari' => $hari,
            'jampraktek' => $jampraktek,
        ]));
    }

    /**
     * Daftar kode poli fingerprint (di-cache) untuk pesan di pendaftaran.
     * Field kode poli diambil dari kodepoli / kdpoli / kodesubspesialis, mana yang tersedia.
     * Hasil berhasil (code 200) di-cache sesuai cache_poli_fp_minutes. Jika gagal, kegagalan
     * hanya diingat 1 menit: cukup untuk tidak menunggu timeout di setiap halaman,
     * tetapi tidak menghilangkan pesan fingerprint terlalu lama.
     */
    public function daftarKodePoliFingerprint(): array
    {
        $tersimpan = Cache::get('antrol.poli_fp');
        if (is_array($tersimpan)) {
            return $tersimpan;
        }
        if ($this->referensiPoliFingerprintGagal()) {
            return [];
        }

        $hasil = $this->referensiPoliFingerprint();
        $kode = $this->ambilKodePoli($hasil);
        if (self::berhasil($hasil)) {
            Cache::put('antrol.poli_fp', $kode, now()->addMinutes((int) config('antrol.cache_poli_fp_minutes', 60)));
        } else {
            Cache::put('antrol.poli_fp_gagal', self::pesan($hasil), now()->addMinute());
        }

        return $kode;
    }

    /**
     * Panggil Referensi Poli Fingerprint tanpa cache (tombol "Cek poli fingerprint").
     * Jika berhasil, cache daftar poli fingerprint ikut diperbarui; jika gagal, cache lama tidak diubah.
     */
    public function perbaruiPoliFingerprint(): array
    {
        $hasil = $this->referensiPoliFingerprint();
        if (self::berhasil($hasil)) {
            Cache::put('antrol.poli_fp', $this->ambilKodePoli($hasil), now()->addMinutes((int) config('antrol.cache_poli_fp_minutes', 60)));
            Cache::forget('antrol.poli_fp_gagal');
        }

        return $hasil;
    }

    /** True jika pengambilan Referensi Poli Fingerprint terakhir gagal (dalam 1 menit terakhir). */
    public function referensiPoliFingerprintGagal(): bool
    {
        return Cache::has('antrol.poli_fp_gagal');
    }

    /** @return list<string> */
    private function ambilKodePoli(array $hasil): array
    {
        $data = $hasil['response'] ?? [];
        if (isset($data['list']) && is_array($data['list'])) {
            $data = $data['list'];
        }
        if (! is_array($data)) {
            return [];
        }

        $kode = [];
        foreach ($data as $baris) {
            if (! is_array($baris)) {
                continue;
            }
            foreach (['kodepoli', 'kdpoli', 'kodesubspesialis', 'kdsubspesialis'] as $k) {
                if (! empty($baris[$k])) {
                    $kode[] = strtoupper((string) $baris[$k]);
                }
            }
        }

        return array_values(array_unique($kode));
    }

    public function poliWajibFingerprint(string $kodepoli): bool
    {
        return in_array(strtoupper($kodepoli), $this->daftarKodePoliFingerprint(), true);
    }

    /**
     * Susun body sesuai urutan field di config. Field yang tidak ada di $data tidak dikirim.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function susunBody(string $service, array $data): array
    {
        $field = $this->body[$service] ?? array_keys($data);
        $out = [];
        foreach ($field as $f) {
            if (array_key_exists($f, $data)) {
                $out[$f] = $data[$f];
            }
        }

        return $out;
    }

    private function isi(string $pola, array $nilai): string
    {
        foreach ($nilai as $k => $v) {
            $pola = str_replace('{'.$k.'}', str_replace(' ', '%20', trim((string) $v)), $pola);
        }

        return $pola;
    }
}
