<?php

namespace App\Repositories;

use App\Services\Antrol\SumberDataTidakTersedia;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Sumber data antrean poli & farmasi.
 * - LocalAntreanRepository (ANTROL_SUMBER_DATA=lokal): tabel data uji modul ini.
 * - BpjsAntreanRepository (ANTROL_SUMBER_DATA=bpjs): antrean farmasi diperiksa ke BPJS dan data resep
 *   yang dikirim SIMRS vendor lewat API internal. Modul tidak terhubung ke database vendor.
 *
 * Bentuk data antrean poli lengkap (dipakai halaman Pendaftaran, Poli, Farmasi):
 *
 * @phpstan-type AntreanPoli array{
 *   kodebooking: string, tanggal: string, nomorantrean: ?string, angkaantrean: ?int,
 *   norm: ?string, nama: ?string, nomorkartu: ?string, nik: ?string, nohp: ?string,
 *   jenispasien: string, kodepoli: ?string, namapoli: ?string, kodedokter: ?string,
 *   namadokter: ?string, jampraktek: ?string, jeniskunjungan: ?int, nomorreferensi: ?string,
 *   status: string, wajib_fp: bool, jenisresep: ?string,
 *   checkin_at: ?string, dipanggil_at: ?string, mulai_at: ?string, selesai_at: ?string,
 *   dikirim_bpjs: bool
 * }
 *
 * Status antrean poli: terdaftar → checkin → dipanggil → dilayani → selesai, atau batal.
 * @phpstan-type Pasien array{norm: string, nama: string, nomorkartu: ?string, nik: ?string, nohp: ?string, tanggal_lahir: ?string}
 * @phpstan-type Jadwal array{
 *   id: string, kodepoli: string, namapoli: string, kodedokter: string, namadokter: string,
 *   hari: int, jampraktek: string, kuota_jkn: int, kuota_nonjkn: int, terisi_jkn: int, terisi_nonjkn: int,
 *   sumber: string
 * }
 * @phpstan-type AntreanFarmasi array{
 *   kodebooking: string, tanggal: string, nomorantrean: int, jenisresep: string, status: string,
 *   keterangan: ?string, dikirim_bpjs: bool, sumber: string, nama: ?string, norm: ?string, namapoli: ?string,
 *   diambil_at: ?string, dipanggil_at: ?string, selesai_at: ?string, batal_at: ?string,
 *   task6_at: ?string, task6_pesan: ?string, task7_at: ?string, task7_pesan: ?string
 * }
 * @phpstan-type Resep array{
 *   kodebooking: string, jenisresep: string, norekammedis: ?string, namapasien: ?string, nomorkartu: ?string,
 *   kodepoli: ?string, tanggal: string, sumber: string, dibatalkan: bool
 * }
 */
interface AntreanRepository
{
    /* ------------------------------------------------------------------
     | Kontrak WSRS (dipakai AntreanFarmasiService). Jangan diubah.
     * ------------------------------------------------------------------ */

    /**
     * Data antrean poli berdasarkan kode booking.
     * Kembalikan null jika tidak ditemukan, atau array:
     * [
     *   'kodebooking' => string,
     *   'tanggal'     => 'Y-m-d',           // tanggal antrean poli
     *   'batal'       => bool,
     *   'checkin'     => bool,
     *   'taskid5'     => bool,              // pelayanan poli sudah selesai
     *   'jenisresep'  => 'Racikan'|'Non Racikan'|'Tidak ada'|null,
     *   'resep_belum_ada' => bool,          // opsional: data resep belum dikirim (mode 'bpjs')
     *   'nama'        => ?string,           // opsional: disalin ke antrean farmasi
     *   'norm'        => ?string,
     * ]
     *
     * @throws SumberDataTidakTersedia jika sumber data (BPJS) tidak dapat diperiksa
     */
    public function cariAntreanPoli(string $kodebooking): ?array;

    /** Antrean farmasi milik kode booking pada tanggal tertentu, atau null. */
    public function cariAntreanFarmasi(string $kodebooking, string $tanggal): ?array;

    /**
     * Buat antrean farmasi baru dengan nomor urut berikutnya pada tanggal tsb.
     * $sumber: 'onsite' (diambil di RS) atau 'wsrs' (diambil lewat Mobile JKN).
     *
     * @param  array{nama?: ?string, norm?: ?string}  $pasien
     */
    public function buatAntreanFarmasi(string $kodebooking, string $tanggal, string $jenisresep, string $keterangan = '', string $sumber = 'onsite', array $pasien = []): array;

    /**
     * Statistik antrean farmasi pada tanggal tsb. Antrean batal tidak dihitung di total & sisa.
     * ['total' => int, 'sisa' => int (menunggu), 'dipanggil' => int (nomor yang sedang/terakhir dipanggil, 0 jika belum ada),
     *  'selesai' => int, 'batal' => int]
     */
    public function statistikFarmasi(string $tanggal): array;

    /* ------------------------------------------------------------------
     | Pendaftaran
     * ------------------------------------------------------------------ */

    /**
     * Cari pasien berdasarkan No. RM, nomor kartu, NIK, atau sebagian nama.
     *
     * @return list<Pasien>
     */
    public function cariPasien(string $kata, int $batas = 20): array;

    /** @return Pasien|null */
    public function pasienByNorm(string $norm): ?array;

    /**
     * Simpan pasien baru.
     *
     * @param  array{norm: string, nama: string, nomorkartu?: ?string, nik?: ?string, nohp?: ?string, tanggal_lahir?: ?string}  $data
     * @return Pasien
     */
    public function simpanPasien(array $data): array;

    /**
     * Jadwal praktek dokter yang berlaku pada tanggal tsb, beserta jumlah kuota terisi.
     * Memakai hasil sinkron BPJS jika ada (lihat ringkasanSumberJadwal), tanpa jadwal libur.
     *
     * @return list<Jadwal>
     */
    public function daftarJadwal(string $tanggal, ?string $kodepoli = null): array;

    /**
     * Semua jadwal praktek (untuk pilihan di Monitoring), tanpa hitungan kuota terisi.
     *
     * @return list<Jadwal>
     */
    public function semuaJadwal(): array;

    /** @return Jadwal|null */
    public function jadwalById(string $id, string $tanggal): ?array;

    /**
     * Simpan hasil sinkron Referensi Jadwal Dokter BPJS untuk satu poli pada satu hari.
     * Jadwal hasil sinkron sebelumnya untuk poli & hari yang sama diganti; data fiktif tidak disentuh.
     *
     * @param  list<array{kodepoli: string, namapoli: string, kodedokter: string, namadokter: string, jampraktek: string, kuota_jkn: int, libur: bool}>  $jadwal
     * @return int jumlah jadwal yang tersimpan
     */
    public function simpanJadwalSinkron(int $hari, string $kodepoli, array $jadwal): int;

    /** Catat poli yang punya jadwal pada tanggal tsb (hasil sinkron), untuk sinkron berikutnya. */
    public function catatPoliBerjadwal(string $kodepoli, string $namapoli, string $tanggal): void;

    /**
     * Poli yang pernah punya jadwal menurut hasil sinkron: [kodepoli => namapoli].
     *
     * @return array<string, string>
     */
    public function daftarPoliBerjadwal(): array;

    /** Hapus semua jadwal fiktif (seeder/manual). Mengembalikan jumlah yang dihapus. */
    public function hapusJadwalFiktif(): int;

    /**
     * Sumber jadwal yang sedang dipakai Pendaftaran & Monitoring: 'bpjs' jika sudah ada hasil sinkron,
     * selain itu 'fiktif'.
     *
     * @return array{sumber: string, jumlah_bpjs: int, jumlah_fiktif: int, terakhir_sinkron: ?string}
     */
    public function ringkasanSumberJadwal(): array;

    /**
     * Buat antrean poli: kode booking unik dan nomor antrean urut per poli per tanggal.
     *
     * @param  array{pasien: Pasien, jadwal: Jadwal, tanggal: string, jenispasien: string, jeniskunjungan: ?int, nomorreferensi: ?string, wajib_fp: bool}  $data
     * @return AntreanPoli
     */
    public function buatAntreanPoli(array $data): array;

    /* ------------------------------------------------------------------
     | Poli
     * ------------------------------------------------------------------ */

    /** @return AntreanPoli|null */
    public function antreanPoliLengkap(string $kodebooking): ?array;

    /**
     * Antrean poli pada tanggal tsb, urut poli lalu nomor antrean.
     *
     * @return list<AntreanPoli>
     */
    public function daftarAntreanPoli(string $tanggal, ?string $kodepoli = null, ?string $kodedokter = null): array;

    /**
     * Simpan status baru antrean poli beserta waktunya. Validasi urutan ada di PelayananService.
     * $jenisresep wajib saat status 'selesai'.
     *
     * @return AntreanPoli
     */
    public function ubahStatusAntreanPoli(string $kodebooking, string $status, ?string $jenisresep = null): array;

    /** Tandai antrean poli sudah terkirim ke BPJS (Tambah Antrean, mode simulasi). */
    public function tandaiAntreanPoliTerkirim(string $kodebooking): void;

    /* ------------------------------------------------------------------
     | Farmasi
     * ------------------------------------------------------------------ */

    /**
     * Antrean poli yang sudah selesai (task ID 5), punya resep Racikan / Non Racikan,
     * belum batal, dan belum mengambil antrean farmasi pada tanggal tsb.
     *
     * @return list<AntreanPoli>
     */
    public function daftarSiapAmbilFarmasi(string $tanggal): array;

    /** @return list<AntreanFarmasi> */
    public function daftarAntreanFarmasi(string $tanggal): array;

    /**
     * Simpan antrean farmasi dengan nomor yang sudah ditentukan pihak lain (Ambil Antrean Farmasi SIMRS vendor).
     *
     * @param  array{nama?: ?string, norm?: ?string}  $pasien
     *
     * @throws UniqueConstraintViolationException jika nomor atau kode booking sudah dipakai pada tanggal tsb
     */
    public function simpanAntreanFarmasiBernomor(string $kodebooking, string $tanggal, int $nomorantrean, string $jenisresep, string $keterangan, string $sumber, array $pasien = []): array;

    /** Ubah status antrean farmasi (menunggu | dipanggil | selesai | batal) beserta waktunya. */
    public function ubahStatusFarmasi(string $kodebooking, string $tanggal, string $status): void;

    /** Catat hasil pengiriman Update Waktu task ID 6/7 farmasi ke BPJS. */
    public function tandaiTaskFarmasi(string $kodebooking, string $tanggal, int $taskid, bool $berhasil, ?string $pesan = null): void;

    /* ------------------------------------------------------------------
     | Resep (dikirim SIMRS vendor lewat API internal, atau input manual farmasi)
     * ------------------------------------------------------------------ */

    /**
     * Simpan atau perbarui data resep satu kode booking. Kiriman ulang memperbarui data dan
     * membatalkan penanda batal sebelumnya.
     *
     * @param  array{kodebooking: string, jenisresep: string, norekammedis?: ?string, namapasien?: ?string, nomorkartu?: ?string, kodepoli?: ?string, tanggal?: ?string}  $data
     * @return Resep
     */
    public function simpanResep(array $data, string $sumber): array;

    /** @return Resep|null */
    public function cariResep(string $kodebooking): ?array;

    /**
     * Tandai resep dibatalkan. Null jika resep tidak ada.
     *
     * @return Resep|null
     */
    public function batalkanResep(string $kodebooking): ?array;

    /** Tandai antrean farmasi sudah terkirim ke BPJS (Tambah Antrean Farmasi berhasil). */
    public function tandaiFarmasiTerkirim(string $kodebooking, string $tanggal): void;
}
