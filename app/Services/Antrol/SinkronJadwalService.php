<?php

namespace App\Services\Antrol;

use App\Repositories\AntreanRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Sleep;

/**
 * Sinkron jadwal dokter dari BPJS (HFIS) ke tabel jadwal uji:
 * Referensi Poli (atau daftar poli yang pernah punya jadwal), lalu Referensi Jadwal Dokter
 * tiap poli untuk satu tanggal, dengan jeda antar-panggilan.
 * Setiap panggilan tercatat di log bridging oleh AntrolClient.
 */
class SinkronJadwalService
{
    /** Kuota JKN jika respons jadwal tidak memuat kapasitas pasien. */
    private const KUOTA_JKN_DEFAULT = 30;

    public function __construct(private WsbpjsService $ws, private AntreanRepository $repo) {}

    public const MODE_BERJADWAL = 'berjadwal';

    public const MODE_SEMUA = 'semua';

    /**
     * $mode 'berjadwal': hanya poli yang pernah punya jadwal (tanpa memanggil Referensi Poli).
     * $mode 'semua': seluruh poli dari Referensi Poli.
     *
     * @return array{
     *   ok: bool, pesan: string, tanggal: string, hari: int, mode: string,
     *   jumlah_poli: int, poli_berhasil: int, jumlah_jadwal: int, jumlah_libur: int, dilewati: int,
     *   tanpa_jadwal: list<array{kodepoli: string, namapoli: string}>,
     *   gagal: list<array{kodepoli: string, namapoli: string, jenis: string, pesan: string}>,
     *   tidak_diproses: int, dihentikan: bool, fiktif_dihapus: ?int
     * }
     */
    public function sinkron(string $tanggal, bool $gantiFiktif = false, string $mode = self::MODE_SEMUA): array
    {
        $hari = Carbon::parse($tanggal)->dayOfWeekIso;
        $ringkasan = [
            'ok' => false, 'pesan' => '', 'tanggal' => $tanggal, 'hari' => $hari, 'mode' => $mode,
            'jumlah_poli' => 0, 'poli_berhasil' => 0, 'jumlah_jadwal' => 0, 'jumlah_libur' => 0, 'dilewati' => 0,
            'tanpa_jadwal' => [], 'gagal' => [], 'tidak_diproses' => 0, 'dihentikan' => false, 'fiktif_dihapus' => null,
        ];

        if ($mode === self::MODE_BERJADWAL) {
            $daftarPoli = $this->repo->daftarPoliBerjadwal();
            if (! $daftarPoli) {
                $ringkasan['pesan'] = 'Belum ada daftar poli yang pernah punya jadwal. Jalankan sinkron "Semua poli" terlebih dahulu.';

                return $ringkasan;
            }
        } else {
            $hasilPoli = $this->ws->referensiPoli();
            if (! WsbpjsService::berhasil($hasilPoli)) {
                $ringkasan['pesan'] = 'Referensi Poli gagal diambil: '.WsbpjsService::pesan($hasilPoli).'. Jadwal tidak diubah.';

                return $ringkasan;
            }
            $daftarPoli = $this->daftarPoli($hasilPoli);
            if (! $daftarPoli) {
                $ringkasan['pesan'] = 'Referensi Poli dari BPJS kosong. Jadwal tidak diubah.';

                return $ringkasan;
            }
        }
        $ringkasan['jumlah_poli'] = count($daftarPoli);

        $jedaMs = max(0, (int) config('antrol.sinkron_jadwal.jeda_ms', 100));
        $batasGagalKoneksi = max(0, (int) config('antrol.sinkron_jadwal.batas_gagal_koneksi', 5));
        $gagalKoneksiBeruntun = 0;
        $diproses = 0;
        $sudahMemanggil = $mode !== self::MODE_BERJADWAL;

        foreach ($daftarPoli as $kodepoli => $namapoli) {
            $kodepoli = (string) $kodepoli;
            if ($batasGagalKoneksi > 0 && $gagalKoneksiBeruntun >= $batasGagalKoneksi) {
                $ringkasan['dihentikan'] = true;
                break;
            }
            if ($sudahMemanggil && $jedaMs > 0) {
                Sleep::for($jedaMs)->milliseconds();
            }
            $sudahMemanggil = true;
            $diproses++;

            $hasilJadwal = $this->ws->referensiJadwalDokter($kodepoli, $tanggal);
            $gagalKoneksi = $this->gagalKoneksi($hasilJadwal);
            $gagalKoneksiBeruntun = $gagalKoneksi ? $gagalKoneksiBeruntun + 1 : 0;

            if (WsbpjsService::berhasil($hasilJadwal)) {
                [$jadwal, $dilewati] = $this->daftarJadwal($hasilJadwal, $namapoli);
                $ringkasan['jumlah_jadwal'] += $this->repo->simpanJadwalSinkron($hari, $kodepoli, $jadwal);
                $ringkasan['jumlah_libur'] += count(array_filter($jadwal, fn (array $j) => $j['libur']));
                $ringkasan['dilewati'] += $dilewati;
                $ringkasan['poli_berhasil']++;
                if ($jadwal) {
                    $this->repo->catatPoliBerjadwal($kodepoli, $namapoli, $tanggal);
                }
            } elseif ($this->tidakAdaJadwal($hasilJadwal)) {
                // BPJS menyatakan poli tidak punya jadwal pada tanggal ini: jadwal lama hari ini dihapus.
                $this->repo->simpanJadwalSinkron($hari, $kodepoli, []);
                $ringkasan['tanpa_jadwal'][] = ['kodepoli' => $kodepoli, 'namapoli' => $namapoli];
            } else {
                $ringkasan['gagal'][] = [
                    'kodepoli' => $kodepoli,
                    'namapoli' => $namapoli,
                    'jenis' => $gagalKoneksi ? 'koneksi' : 'bpjs',
                    'pesan' => WsbpjsService::pesan($hasilJadwal),
                ];
            }
        }
        $ringkasan['tidak_diproses'] = $ringkasan['jumlah_poli'] - $diproses;

        if ($gantiFiktif) {
            $ringkasan['fiktif_dihapus'] = $ringkasan['jumlah_jadwal'] > 0 ? $this->repo->hapusJadwalFiktif() : 0;
        }

        $ringkasan['ok'] = $ringkasan['poli_berhasil'] + count($ringkasan['tanpa_jadwal']) > 0;
        $ringkasan['pesan'] = $this->pesan($ringkasan, $gantiFiktif, $batasGagalKoneksi);

        return $ringkasan;
    }

    /** True jika tidak ada jawaban dari BPJS sama sekali (DNS, timeout, koneksi ditolak). */
    private function gagalKoneksi(array $hasil): bool
    {
        return ! empty($hasil['error']) && $hasil['http_status'] === null;
    }

    /** True jika jawaban BPJS berarti poli tidak punya jadwal (lihat config antrol.respons.tidak_ada_jadwal). */
    private function tidakAdaJadwal(array $hasil): bool
    {
        $aturan = (array) config('antrol.respons.tidak_ada_jadwal', []);
        $kode = $hasil['metadata']['code'] ?? null;
        if ($kode === null || ! in_array((int) $kode, array_map('intval', (array) ($aturan['code'] ?? [])), true)) {
            return false;
        }

        $pesan = strtolower((string) ($hasil['metadata']['message'] ?? ''));
        foreach ((array) ($aturan['pesan'] ?? []) as $pola) {
            if ($pola !== '' && str_contains($pesan, strtolower((string) $pola))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Poli unik dari Referensi Poli: [kode => nama]. Kode diambil dari field subspesialis lebih dulu.
     *
     * @return array<string, string>
     */
    private function daftarPoli(array $hasil): array
    {
        $field = (array) config('antrol.respons.ref_poli', []);
        $poli = [];
        foreach ($this->baris($hasil) as $baris) {
            $kode = strtoupper($this->ambil($baris, $field['kodepoli'] ?? []));
            if ($kode !== '' && ! isset($poli[$kode])) {
                $poli[$kode] = $this->ambil($baris, $field['namapoli'] ?? []) ?: $kode;
            }
        }

        return $poli;
    }

    /**
     * Jadwal dari respons Referensi Jadwal Dokter. Baris tanpa kode dokter atau jam praktek dilewati.
     *
     * @return array{0: list<array{kodepoli: string, namapoli: string, kodedokter: string, namadokter: string, jampraktek: string, kuota_jkn: int, libur: bool}>, 1: int}
     */
    private function daftarJadwal(array $hasil, string $namapoliRef): array
    {
        $field = (array) config('antrol.respons.ref_jadwal_dokter', []);
        $jadwal = [];
        $dilewati = 0;
        foreach ($this->baris($hasil) as $baris) {
            $kodedokter = $this->ambil($baris, $field['kodedokter'] ?? []);
            $jampraktek = preg_replace('/\s+/', '', $this->ambil($baris, $field['jampraktek'] ?? []));
            if ($kodedokter === '' || $jampraktek === '') {
                $dilewati++;

                continue;
            }

            $kuota = $this->ambil($baris, $field['kuota'] ?? []);
            $jadwal[] = [
                'kodepoli' => strtoupper($this->ambil($baris, $field['kodepoli'] ?? [])),
                'namapoli' => $this->ambil($baris, $field['namapoli'] ?? []) ?: $namapoliRef,
                'kodedokter' => $kodedokter,
                'namadokter' => $this->ambil($baris, $field['namadokter'] ?? []) ?: $kodedokter,
                'jampraktek' => $jampraktek,
                'kuota_jkn' => is_numeric($kuota) ? (int) $kuota : self::KUOTA_JKN_DEFAULT,
                'libur' => in_array(strtolower($this->ambil($baris, $field['libur'] ?? [])), ['1', 'true'], true),
            ];
        }

        return [$jadwal, $dilewati];
    }

    /**
     * Baris data respons: daftar langsung atau dibungkus "list".
     *
     * @return list<array<string, mixed>>
     */
    private function baris(array $hasil): array
    {
        $data = $hasil['response'] ?? [];
        if (is_array($data) && isset($data['list']) && is_array($data['list'])) {
            $data = $data['list'];
        }

        return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
    }

    /**
     * Nilai field pertama yang terisi dari daftar nama field.
     *
     * @param  array<string, mixed>  $baris
     * @param  list<string>  $namaField
     */
    private function ambil(array $baris, array $namaField): string
    {
        foreach ($namaField as $nama) {
            if (isset($baris[$nama]) && ! is_array($baris[$nama]) && trim((string) $baris[$nama]) !== '') {
                return trim(is_bool($baris[$nama]) ? ($baris[$nama] ? '1' : '0') : (string) $baris[$nama]);
            }
        }

        return '';
    }

    /** @param  array<string, mixed>  $r */
    private function pesan(array $r, bool $gantiFiktif, int $batasGagalKoneksi): string
    {
        $diproses = $r['jumlah_poli'] - $r['tidak_diproses'];
        $pesan = "Sinkron jadwal tanggal {$r['tanggal']}: {$diproses} dari {$r['jumlah_poli']} poli diproses. "
            ."{$r['poli_berhasil']} berhasil ({$r['jumlah_jadwal']} jadwal tersimpan), "
            .count($r['tanpa_jadwal']).' tidak ada jadwal, '.count($r['gagal']).' gagal.';

        if ($r['dihentikan']) {
            $pesan .= " Sinkron dihentikan setelah {$batasGagalKoneksi} kali gagal koneksi berturut-turut; "
                ."{$r['tidak_diproses']} poli tidak diproses. Periksa koneksi ke BPJS lalu sinkron ulang.";
        }
        if (! $r['ok']) {
            $pesan .= ' Jadwal tidak diubah.';
        }
        if ($gantiFiktif) {
            $pesan .= $r['jumlah_jadwal'] > 0
                ? " {$r['fiktif_dihapus']} jadwal fiktif dihapus."
                : ' Data fiktif tidak dihapus karena belum ada jadwal hasil sinkron yang tersimpan.';
        }

        return $pesan;
    }
}
