<?php

namespace App\Services\Antrol;

use App\Models\Antrol\AntreanFarmasi;
use App\Repositories\AntreanRepository;

/**
 * Aturan antrean farmasi sesuai Term and Condition form UAT:
 * - antrean farmasi dapat diambil jika RS sudah update task ID 5 dan mengisi jenis resep
 * - kode booking yang dipakai adalah kode booking poli
 * - tanggal antrean farmasi = tanggal antrean poli
 * - nomor antrean bertambah dari antrean sebelumnya
 * - 1 kode booking hanya dapat mengambil 1 antrean per hari
 */
class AntreanFarmasiService
{
    public function __construct(private AntreanRepository $repo) {}

    /**
     * Ambil antrean farmasi. $sumber 'wsrs' untuk WSRS ambilantreanfarmasi (Mobile JKN),
     * 'onsite' untuk pengambilan di RS. Isi respons sama untuk kedua sumber.
     */
    public function ambil(?string $kodebooking, string $keterangan = '', string $sumber = AntreanFarmasi::SUMBER_ONSITE): array
    {
        $cek = $this->periksa($kodebooking);
        if ($cek['gagal'] !== null) {
            return $cek['gagal'];
        }

        $baru = $this->repo->buatAntreanFarmasi($cek['kodebooking'], $cek['tanggal'], $cek['jenisresep'], $keterangan, $sumber, $cek['pasien']);

        return Hasil::ok([
            'jenisresep' => $cek['jenisresep'],
            'nomorantrean' => (int) $baru['nomorantrean'],
            'keterangan' => '',
        ]);
    }

    /**
     * Semua aturan ambil antrean farmasi tanpa membuat antrean: task ID 5 & jenis resep (data poli / BPJS),
     * tanggal, batal, dan 1 kode booking 1 antrean per hari. Dipakai juga sebelum nomor diambil dari SIMRS vendor.
     *
     * @return array{gagal: ?array, kodebooking: string, tanggal: string, jenisresep: string, pasien: array{nama: ?string, norm: ?string}}
     */
    public function periksa(?string $kodebooking): array
    {
        $kodebooking = trim((string) $kodebooking);
        $tolak = fn (string $pesan) => ['gagal' => Hasil::gagal($pesan), 'kodebooking' => $kodebooking, 'tanggal' => '', 'jenisresep' => '', 'pasien' => ['nama' => null, 'norm' => null]];
        if ($kodebooking === '') {
            return $tolak('Kode Booking tidak boleh kosong');
        }

        try {
            $poli = $this->repo->cariAntreanPoli($kodebooking);
        } catch (SumberDataTidakTersedia $e) {
            return $tolak($e->getMessage());
        }
        if (! $poli) {
            return $tolak('Kode Booking tidak ditemukan');
        }
        if ($poli['batal']) {
            return $tolak('Antrean dengan kode booking tersebut sudah dibatalkan');
        }

        $hariIni = now()->toDateString();
        if ($poli['tanggal'] !== $hariIni) {
            return $tolak('Antrean farmasi hanya dapat diambil pada tanggal antrean poli ('.$poli['tanggal'].')');
        }
        if (! $poli['taskid5']) {
            return $tolak('Pelayanan poli belum selesai, antrean farmasi belum dapat diambil');
        }
        if ($poli['resep_belum_ada'] ?? false) {
            return $tolak('Jenis resep belum tersedia, silakan hubungi farmasi');
        }

        $jenis = $this->normalisasiJenisResep($poli['jenisresep']);
        if ($jenis === null) {
            return $tolak('Pasien tidak memiliki resep, antrean farmasi tidak dapat diambil');
        }

        $ada = $this->repo->cariAntreanFarmasi($kodebooking, $hariIni);
        if ($ada) {
            return $tolak('Kode booking sudah mengambil antrean farmasi hari ini dengan nomor '.$ada['nomorantrean']);
        }

        return [
            'gagal' => null,
            'kodebooking' => $kodebooking,
            'tanggal' => $hariIni,
            'jenisresep' => $jenis,
            'pasien' => ['nama' => $poli['nama'] ?? null, 'norm' => $poli['norm'] ?? null],
        ];
    }

    public function status(?string $kodebooking): array
    {
        $kodebooking = trim((string) $kodebooking);
        if ($kodebooking === '') {
            return Hasil::gagal('Kode Booking tidak boleh kosong');
        }

        try {
            $poli = $this->repo->cariAntreanPoli($kodebooking);
        } catch (SumberDataTidakTersedia $e) {
            return Hasil::gagal($e->getMessage());
        }
        if (! $poli) {
            return Hasil::gagal('kodebooking Tidak Ditemukan');
        }

        $farmasi = $this->repo->cariAntreanFarmasi($kodebooking, $poli['tanggal']);
        if (! $farmasi) {
            return Hasil::gagal('Kode booking belum mengambil antrean farmasi');
        }

        $stat = $this->repo->statistikFarmasi($poli['tanggal']);

        return Hasil::ok([
            'jenisresep' => $farmasi['jenisresep'],
            'totalantrean' => (int) $stat['total'],
            'sisaantrean' => (int) $stat['sisa'],
            'antreanpanggil' => (int) $stat['dipanggil'],
            'keterangan' => '',
        ]);
    }

    /** 'Racikan' / 'Non Racikan', atau null jika tidak ada resep. */
    public function normalisasiJenisResep(?string $jenis): ?string
    {
        $j = strtolower(trim((string) $jenis));

        return match ($j) {
            'racikan' => 'Racikan',
            'non racikan', 'nonracikan' => 'Non Racikan',
            default => null,
        };
    }
}
