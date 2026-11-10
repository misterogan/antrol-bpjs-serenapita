<?php

namespace App\Services\Antrol;

use App\Models\Antrol\AntreanFarmasi;
use App\Repositories\AntreanRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

/**
 * Alur pelayanan rawat jalan pasien JKN: pendaftaran → poli → farmasi.
 * Semua data lewat AntreanRepository. Pengiriman Tambah Antrean & Update Waktu
 * ke BPJS hanya dilakukan saat mode simulasi aktif.
 *
 * Setiap aksi mengembalikan:
 * ['ok' => bool, 'pesan' => string, 'peringatan' => ?string, 'data' => ?array, 'bpjs' => list<array>]
 */
class PelayananService
{
    /** Urutan status antrean poli per aksi tombol: aksi => [status asal yang diizinkan, status baru, task ID]. */
    public const AKSI_POLI = [
        'checkin' => [['terdaftar'], 'checkin', 3],
        'panggil' => [['checkin', 'dipanggil'], 'dipanggil', null],
        'mulai' => [['dipanggil'], 'dilayani', 4],
        'selesai' => [['dilayani'], 'selesai', 5],
        'batal' => [['terdaftar', 'checkin', 'dipanggil', 'dilayani'], 'batal', null],
    ];

    public const JENIS_RESEP = ['Racikan', 'Non Racikan', 'Tidak ada'];

    public const JENIS_KUNJUNGAN = [
        1 => 'Rujukan FKTP',
        2 => 'Rujukan Internal',
        3 => 'Kontrol',
        4 => 'Rujukan Antar RS',
    ];

    public function __construct(
        private AntreanRepository $repo,
        private WsbpjsService $ws,
        private AntreanFarmasiService $farmasi,
        private VendorWsrsClient $vendor,
    ) {}

    public static function modeSimulasi(): bool
    {
        // Mode simulasi hanya berlaku jika alat simulasi ditampilkan (ANTROL_TAMPILKAN_ALAT_SIMULASI=true).
        return self::alatSimulasi() && (bool) config('antrol.mode_simulasi', false);
    }

    /** True jika alat simulasi (pendaftaran simulasi, poli simulasi, data uji) ditampilkan. */
    public static function alatSimulasi(): bool
    {
        return (bool) config('antrol.tampilkan_alat_simulasi', false);
    }

    /* ----------------------------- Pendaftaran ---------------------------- */

    /**
     * Referensi Pasien Fingerprint: pakai nomor kartu jika ada, jika tidak NIK.
     *
     * @param  array{nomorkartu: ?string, nik: ?string}  $pasien
     * @return array{jenis: string, nomor: string, hasil: array}|null
     */
    public function cekFingerprintPasien(array $pasien): ?array
    {
        [$jenis, $nomor] = ! empty($pasien['nomorkartu'])
            ? ['noka', (string) $pasien['nomorkartu']]
            : (! empty($pasien['nik']) ? ['nik', (string) $pasien['nik']] : [null, null]);

        if ($jenis === null) {
            return null;
        }

        return ['jenis' => $jenis, 'nomor' => $nomor, 'hasil' => $this->ws->referensiPasienFingerprint($jenis, $nomor)];
    }

    /**
     * Validasi pendaftaran sebelum disimpan dan tentukan apakah poli tujuan wajib fingerprint.
     *
     * @param  array{norm: string, jadwal_id: string, tanggal: string, jenispasien: string}  $input
     * @return array{ok: bool, pesan: string, pasien: ?array, jadwal: ?array, wajib_fp: bool}
     */
    public function periksaPendaftaran(array $input): array
    {
        $gagal = fn (string $pesan, ?array $pasien = null, ?array $jadwal = null) => [
            'ok' => false, 'pesan' => $pesan, 'pasien' => $pasien, 'jadwal' => $jadwal, 'wajib_fp' => false,
        ];

        $pasien = $this->repo->pasienByNorm($input['norm']);
        if (! $pasien) {
            return $gagal('Pasien dengan No. RM '.$input['norm'].' tidak ditemukan');
        }

        $jadwal = $this->repo->jadwalById($input['jadwal_id'], $input['tanggal']);
        if (! $jadwal) {
            return $gagal('Jadwal praktek tidak tersedia pada tanggal tersebut', $pasien);
        }

        if ($input['jenispasien'] === 'JKN' && empty($pasien['nomorkartu'])) {
            return $gagal('Pasien JKN wajib memiliki nomor kartu BPJS', $pasien, $jadwal);
        }

        $jkn = $input['jenispasien'] === 'JKN';
        $sisa = $jkn ? $jadwal['kuota_jkn'] - $jadwal['terisi_jkn'] : $jadwal['kuota_nonjkn'] - $jadwal['terisi_nonjkn'];
        if ($sisa <= 0) {
            return $gagal('Kuota '.($jkn ? 'JKN' : 'non JKN').' jadwal ini sudah penuh', $pasien, $jadwal);
        }

        foreach ($this->repo->daftarAntreanPoli($input['tanggal'], $jadwal['kodepoli']) as $a) {
            if ($a['norm'] === $pasien['norm'] && $a['status'] !== 'batal') {
                return $gagal('Pasien sudah terdaftar di poli '.$jadwal['namapoli'].' pada tanggal ini (kode booking '.$a['kodebooking'].')', $pasien, $jadwal);
            }
        }

        return [
            'ok' => true,
            'pesan' => '',
            'pasien' => $pasien,
            'jadwal' => $jadwal,
            'wajib_fp' => $this->ws->poliWajibFingerprint($jadwal['kodepoli']),
        ];
    }

    /**
     * Simpan pendaftaran. Poli fingerprint wajib sudah dikonfirmasi (pesan sudah ditampilkan).
     *
     * @param  array{norm: string, jadwal_id: string, tanggal: string, jenispasien: string, jeniskunjungan?: ?int, nomorreferensi?: ?string}  $input
     */
    public function daftar(array $input, bool $konfirmasiFingerprint): array
    {
        $cek = $this->periksaPendaftaran($input);
        if (! $cek['ok']) {
            return $this->hasil(false, $cek['pesan']);
        }
        if ($cek['wajib_fp'] && ! $konfirmasiFingerprint) {
            return $this->hasil(false, 'Poli tujuan termasuk poli fingerprint. Tampilkan pesan fingerprint ke pasien sebelum menyimpan pendaftaran.');
        }

        $antrean = $this->repo->buatAntreanPoli([
            'pasien' => $cek['pasien'],
            'jadwal' => $cek['jadwal'],
            'tanggal' => $input['tanggal'],
            'jenispasien' => $input['jenispasien'],
            'jeniskunjungan' => isset($input['jeniskunjungan']) ? (int) $input['jeniskunjungan'] : null,
            'nomorreferensi' => $input['nomorreferensi'] ?? null,
            'wajib_fp' => $cek['wajib_fp'],
        ]);

        $pesan = 'Pendaftaran tersimpan. Kode booking '.$antrean['kodebooking'].', nomor antrean '.$antrean['nomorantrean'].'.';
        if (! self::modeSimulasi()) {
            return $this->hasil(true, $pesan, $antrean);
        }

        $bpjs = $this->kirimTambahAntrean($antrean, $cek['jadwal']);

        return $this->hasil(
            true,
            $pesan,
            $antrean,
            [$bpjs],
            WsbpjsService::berhasil($bpjs) ? null : 'Tambah Antrean ke BPJS gagal: '.WsbpjsService::pesan($bpjs).'. Kirim ulang dari halaman Poli.'
        );
    }

    /** Kirim ulang Tambah Antrean (mode simulasi) untuk antrean yang belum terkirim. */
    public function kirimUlangTambahAntrean(string $kodebooking): array
    {
        if (! self::modeSimulasi()) {
            return $this->hasil(false, 'Mode simulasi tidak aktif. Tambah Antrean dikirim oleh SIMRS utama.');
        }
        $antrean = $this->repo->antreanPoliLengkap($kodebooking);
        if (! $antrean) {
            return $this->hasil(false, 'Kode booking tidak ditemukan');
        }
        $jadwal = null;
        foreach ($this->repo->daftarJadwal($antrean['tanggal'], $antrean['kodepoli']) as $j) {
            if ($j['kodedokter'] === $antrean['kodedokter'] && $j['jampraktek'] === $antrean['jampraktek']) {
                $jadwal = $j;
            }
        }
        if (! $jadwal) {
            return $this->hasil(false, 'Jadwal praktek antrean ini tidak ditemukan, data kuota tidak dapat disusun');
        }

        $bpjs = $this->kirimTambahAntrean($antrean, $jadwal, true);

        return WsbpjsService::berhasil($bpjs)
            ? $this->hasil(true, 'Tambah Antrean '.$kodebooking.' terkirim ke BPJS.', $antrean, [$bpjs])
            : $this->hasil(false, 'Tambah Antrean ke BPJS gagal: '.WsbpjsService::pesan($bpjs), $antrean, [$bpjs]);
    }

    /* -------------------------------- Poli -------------------------------- */

    /**
     * Jalankan tombol di halaman Poli sesuai urutan status.
     * Saat 'selesai', jenis resep wajib diisi (menandai task ID 5).
     */
    public function aksiPoli(string $kodebooking, string $aksi, ?string $jenisresep = null): array
    {
        if (! isset(self::AKSI_POLI[$aksi])) {
            return $this->hasil(false, 'Aksi tidak dikenal');
        }
        $antrean = $this->repo->antreanPoliLengkap($kodebooking);
        if (! $antrean) {
            return $this->hasil(false, 'Kode booking tidak ditemukan');
        }

        [$asal, $statusBaru, $taskid] = self::AKSI_POLI[$aksi];
        if (! in_array($antrean['status'], $asal, true)) {
            return $this->hasil(false, 'Aksi "'.$aksi.'" tidak dapat dilakukan pada antrean berstatus '.$antrean['status'].'.');
        }
        if ($antrean['tanggal'] !== now()->toDateString() && $aksi !== 'batal') {
            return $this->hasil(false, 'Pelayanan poli hanya dapat dilakukan pada tanggal antrean ('.$antrean['tanggal'].').');
        }
        if ($aksi === 'selesai' && ! in_array($jenisresep, self::JENIS_RESEP, true)) {
            return $this->hasil(false, 'Pilih resep terlebih dahulu: Racikan, Non Racikan, atau Tidak ada resep.');
        }

        $baru = $this->repo->ubahStatusAntreanPoli($kodebooking, $statusBaru, $aksi === 'selesai' ? $jenisresep : null);
        $pesan = $this->pesanAksiPoli($aksi, $baru);

        if ($taskid === null || ! self::modeSimulasi()) {
            return $this->hasil(true, $pesan, $baru);
        }

        $bpjs = $this->ws->updateWaktu($kodebooking, $taskid, now()->getTimestampMs(), $aksi === 'selesai' ? $jenisresep : null);

        return $this->hasil(
            true,
            $pesan.' Update Waktu task ID '.$taskid.' dikirim ke BPJS.',
            $baru,
            [$bpjs],
            WsbpjsService::berhasil($bpjs) ? null : 'Update Waktu task ID '.$taskid.' ditolak BPJS: '.WsbpjsService::pesan($bpjs)
        );
    }

    /* ------------------------------- Farmasi ------------------------------ */

    /**
     * Ambil antrean farmasi dengan aturan yang sama seperti WSRS,
     * lalu kirim Tambah Antrean Farmasi ke BPJS.
     */
    public function ambilFarmasi(string $kodebooking, string $keterangan = ''): array
    {
        if (config('antrol.nomor_farmasi_dari', 'vendor') === 'vendor') {
            return $this->ambilFarmasiNomorVendor($kodebooking, $keterangan);
        }

        $lokal = $this->farmasi->ambil($kodebooking, $keterangan);
        if (Hasil::kode($lokal) !== 200) {
            return $this->hasil(false, $lokal['metadata']['message']);
        }

        $nomor = (int) $lokal['response']['nomorantrean'];
        $jenis = $lokal['response']['jenisresep'];
        $pesan = 'Antrean farmasi nomor '.$nomor.' ('.$jenis.') untuk kode booking '.$kodebooking.'.';

        return $this->kirimFarmasi($kodebooking, $jenis, $nomor, $keterangan, $pesan);
    }

    /**
     * ANTROL_NOMOR_FARMASI_DARI=vendor: aturan antrean farmasi (task ID 5 di BPJS, duplikat) diperiksa dulu,
     * nomor diambil dari Ambil Antrean Farmasi SIMRS vendor, disimpan sebagai antrean onsite,
     * lalu Tambah Antrean Farmasi dikirim ke BPJS.
     */
    private function ambilFarmasiNomorVendor(string $kodebooking, string $keterangan): array
    {
        $cek = $this->farmasi->periksa($kodebooking);
        if ($cek['gagal'] !== null) {
            return $this->hasil(false, $cek['gagal']['metadata']['message']);
        }

        $vendor = $this->vendor->ambilAntreanFarmasi($cek['kodebooking']);
        if (! $vendor['ok']) {
            return $this->hasil(false, 'SIMRS vendor: '.$vendor['pesan']);
        }

        $nomor = $vendor['response']['nomorantrean'] ?? null;
        if (! is_numeric($nomor) || (int) $nomor < 1) {
            return $this->hasil(false, 'SIMRS vendor: nomor antrean farmasi tidak valid ('.json_encode($nomor).').');
        }
        $nomor = (int) $nomor;

        try {
            $this->repo->simpanAntreanFarmasiBernomor($cek['kodebooking'], $cek['tanggal'], $nomor, $cek['jenisresep'], $keterangan, AntreanFarmasi::SUMBER_ONSITE, $cek['pasien']);
        } catch (UniqueConstraintViolationException) {
            return $this->hasil(false, 'Nomor antrean farmasi '.$nomor.' dari SIMRS vendor sudah dipakai antrean lain hari ini.');
        }

        $pesan = 'Antrean farmasi nomor '.$nomor.' ('.$cek['jenisresep'].') dari SIMRS vendor untuk kode booking '.$cek['kodebooking'].'.';

        return $this->kirimFarmasi($cek['kodebooking'], $cek['jenisresep'], $nomor, $keterangan, $pesan);
    }

    /** Kirim ulang Tambah Antrean Farmasi untuk antrean farmasi yang belum terkirim. */
    public function kirimUlangFarmasi(string $kodebooking): array
    {
        $hariIni = now()->toDateString();
        $f = $this->repo->cariAntreanFarmasi($kodebooking, $hariIni);
        if (! $f) {
            return $this->hasil(false, 'Kode booking belum mengambil antrean farmasi hari ini');
        }
        if (($f['sumber'] ?? null) === AntreanFarmasi::SUMBER_WSRS) {
            return $this->hasil(false, 'Antrean farmasi nomor '.$f['nomorantrean'].' diambil lewat Mobile JKN (WSRS) dan sudah diketahui BPJS, tidak perlu dikirim.');
        }

        return $this->kirimFarmasi(
            $kodebooking,
            (string) $f['jenisresep'],
            (int) $f['nomorantrean'],
            (string) ($f['keterangan'] ?? ''),
            'Antrean farmasi nomor '.$f['nomorantrean'].' dikirim ulang.'
        );
    }

    /** Urutan status antrean farmasi: aksi => [status asal yang diizinkan, status baru, task ID ke BPJS]. */
    public const AKSI_FARMASI = [
        'panggil' => [[AntreanFarmasi::MENUNGGU, AntreanFarmasi::DIPANGGIL], AntreanFarmasi::DIPANGGIL, 6],
        'selesai' => [[AntreanFarmasi::DIPANGGIL], AntreanFarmasi::SELESAI, 7],
        'batal' => [[AntreanFarmasi::MENUNGGU], AntreanFarmasi::BATAL, null],
    ];

    /**
     * Panggil, selesaikan, atau batalkan antrean farmasi hari ini.
     * Panggil mengirim Update Waktu task ID 6 dan Selesai task ID 7 (config antrol.kirim_task_farmasi);
     * gagal kirim tidak membatalkan perubahan status. Batal tidak mengirim task 99.
     */
    public function aksiFarmasi(string $kodebooking, string $aksi): array
    {
        $hariIni = now()->toDateString();
        $f = $this->repo->cariAntreanFarmasi($kodebooking, $hariIni);
        if (! $f) {
            return $this->hasil(false, 'Antrean farmasi tidak ditemukan');
        }
        if (! isset(self::AKSI_FARMASI[$aksi])) {
            return $this->hasil(false, 'Aksi tidak dikenal');
        }
        [$asal, $statusBaru, $taskid] = self::AKSI_FARMASI[$aksi];
        if (! in_array($f['status'], $asal, true)) {
            return $this->hasil(false, 'Antrean farmasi nomor '.$f['nomorantrean'].' berstatus '.$f['status'].', aksi tidak dapat dilakukan.');
        }

        $this->repo->ubahStatusFarmasi($kodebooking, $hariIni, $statusBaru);
        $pesan = match ($aksi) {
            'panggil' => 'Memanggil antrean farmasi nomor '.$f['nomorantrean'].'.',
            'selesai' => 'Antrean farmasi nomor '.$f['nomorantrean'].' selesai.',
            default => 'Antrean farmasi nomor '.$f['nomorantrean'].' dibatalkan.',
        };

        // Panggil ulang tidak mengirim task 6 lagi jika sebelumnya sudah diterima BPJS.
        $sudahTerkirim = $taskid !== null && ! empty($f['task'.$taskid.'_at']);
        if ($taskid === null || ! config('antrol.kirim_task_farmasi') || $sudahTerkirim) {
            return $this->hasil(true, $pesan);
        }

        // Waktu task = waktu panggil/selesai yang tersimpan, sama dengan yang dipakai saat Kirim ulang task.
        $baru = (array) $this->repo->cariAntreanFarmasi($kodebooking, $hariIni);
        $waktu = $baru[$taskid === 6 ? 'dipanggil_at' : 'selesai_at'] ?? null;
        $bpjs = $this->kirimTaskFarmasi($kodebooking, $hariIni, $taskid, $waktu ? Carbon::parse($waktu) : now());

        return $this->hasil(
            true,
            WsbpjsService::berhasil($bpjs) ? $pesan.' Update Waktu task ID '.$taskid.' terkirim ke BPJS.' : $pesan,
            null,
            [$bpjs],
            WsbpjsService::berhasil($bpjs) ? null : 'Status antrean farmasi tersimpan, tetapi Update Waktu task ID '.$taskid.' ditolak BPJS: '.WsbpjsService::pesan($bpjs).'. Gunakan tombol Kirim ulang task.'
        );
    }

    /** Kirim ulang Update Waktu task ID 6/7 farmasi, memakai waktu panggil/selesai yang tersimpan. */
    public function kirimUlangTaskFarmasi(string $kodebooking, int $taskid): array
    {
        $hariIni = now()->toDateString();
        $f = $this->repo->cariAntreanFarmasi($kodebooking, $hariIni);
        if (! $f) {
            return $this->hasil(false, 'Antrean farmasi tidak ditemukan');
        }

        $waktu = $taskid === 6 ? ($f['dipanggil_at'] ?? null) : ($f['selesai_at'] ?? null);
        $statusBoleh = $taskid === 6 ? [AntreanFarmasi::DIPANGGIL, AntreanFarmasi::SELESAI] : [AntreanFarmasi::SELESAI];
        if (! in_array($f['status'], $statusBoleh, true) || $waktu === null) {
            return $this->hasil(false, 'Task ID '.$taskid.' belum dapat dikirim untuk antrean farmasi berstatus '.$f['status'].'.');
        }

        $bpjs = $this->kirimTaskFarmasi($kodebooking, $hariIni, $taskid, Carbon::parse($waktu));
        if (WsbpjsService::berhasil($bpjs)) {
            return $this->hasil(true, 'Update Waktu task ID '.$taskid.' antrean farmasi nomor '.$f['nomorantrean'].' terkirim ke BPJS.', null, [$bpjs]);
        }

        return $this->hasil(false, 'Update Waktu task ID '.$taskid.' ditolak BPJS: '.WsbpjsService::pesan($bpjs), null, [$bpjs]);
    }

    /* ------------------------------- Bantuan ------------------------------ */

    private function kirimTaskFarmasi(string $kodebooking, string $tanggal, int $taskid, Carbon $waktu): array
    {
        $bpjs = $this->ws->updateWaktu($kodebooking, $taskid, $waktu->getTimestampMs());
        $berhasil = WsbpjsService::berhasil($bpjs);
        $this->repo->tandaiTaskFarmasi($kodebooking, $tanggal, $taskid, $berhasil, $berhasil ? null : WsbpjsService::pesan($bpjs));

        return $bpjs;
    }

    private function kirimFarmasi(string $kodebooking, string $jenis, int $nomor, string $keterangan, string $pesan): array
    {
        // Format jenis resep ke BPJS sama dengan halaman Uji service (huruf kecil).
        $bpjs = $this->ws->tambahAntreanFarmasi($kodebooking, strtolower($jenis), $nomor, $keterangan);

        if (WsbpjsService::berhasil($bpjs)) {
            $this->repo->tandaiFarmasiTerkirim($kodebooking, now()->toDateString());

            return $this->hasil(true, $pesan.' Tambah Antrean Farmasi terkirim ke BPJS.', ['nomorantrean' => $nomor, 'jenisresep' => $jenis], [$bpjs]);
        }

        return $this->hasil(
            true,
            $pesan,
            ['nomorantrean' => $nomor, 'jenisresep' => $jenis],
            [$bpjs],
            'Nomor antrean farmasi sudah diambil, tetapi Tambah Antrean Farmasi ke BPJS gagal: '.WsbpjsService::pesan($bpjs).'. Gunakan tombol Kirim ulang.'
        );
    }

    /**
     * @param  array<string, mixed>  $antrean
     * @param  array<string, mixed>  $jadwal
     */
    private function kirimTambahAntrean(array $antrean, array $jadwal, bool $sudahTercatat = false): array
    {
        // Saat pertama dikirim, jadwal dibaca sebelum antrean ini dibuat, jadi kuota terisi + 1.
        $tambahan = $sudahTercatat ? 0 : 1;
        $jkn = $antrean['jenispasien'] === 'JKN';
        $terisiJkn = $jadwal['terisi_jkn'] + ($jkn ? $tambahan : 0);
        $terisiNonJkn = $jadwal['terisi_nonjkn'] + ($jkn ? 0 : $tambahan);

        $bpjs = $this->ws->tambahAntrean([
            'kodebooking' => $antrean['kodebooking'],
            'jenispasien' => $antrean['jenispasien'],
            'nomorkartu' => (string) $antrean['nomorkartu'],
            'nik' => (string) $antrean['nik'],
            'nohp' => (string) $antrean['nohp'],
            'kodepoli' => $antrean['kodepoli'],
            'namapoli' => $antrean['namapoli'],
            'pasienbaru' => 0,
            'norm' => (string) $antrean['norm'],
            'tanggalperiksa' => $antrean['tanggal'],
            'kodedokter' => is_numeric($antrean['kodedokter']) ? (int) $antrean['kodedokter'] : $antrean['kodedokter'],
            'namadokter' => $antrean['namadokter'],
            'jampraktek' => $antrean['jampraktek'],
            'jeniskunjungan' => (int) ($antrean['jeniskunjungan'] ?? 1),
            'nomorreferensi' => (string) $antrean['nomorreferensi'],
            'nomorantrean' => $antrean['nomorantrean'],
            'angkaantrean' => (int) $antrean['angkaantrean'],
            'estimasidilayani' => $this->estimasiDilayani($antrean),
            'sisakuotajkn' => max(0, $jadwal['kuota_jkn'] - $terisiJkn),
            'kuotajkn' => $jadwal['kuota_jkn'],
            'sisakuotanonjkn' => max(0, $jadwal['kuota_nonjkn'] - $terisiNonJkn),
            'kuotanonjkn' => $jadwal['kuota_nonjkn'],
            'keterangan' => 'Peserta harap datang 30 menit lebih awal.',
        ]);

        if (WsbpjsService::berhasil($bpjs)) {
            $this->repo->tandaiAntreanPoliTerkirim($antrean['kodebooking']);
        }

        return $bpjs;
    }

    /** Jam mulai praktek + (nomor urut - 1) x menit per pasien, dalam milidetik. */
    private function estimasiDilayani(array $antrean): int
    {
        $mulai = explode('-', (string) $antrean['jampraktek'])[0] ?: '08:00';
        $menit = (int) config('antrol.menit_per_pasien', 6);

        return Carbon::parse($antrean['tanggal'].' '.trim($mulai))
            ->addMinutes(max(0, ((int) $antrean['angkaantrean'] - 1) * $menit))
            ->getTimestampMs();
    }

    private function pesanAksiPoli(string $aksi, array $antrean): string
    {
        $no = $antrean['nomorantrean'] ?? $antrean['kodebooking'];

        return match ($aksi) {
            'checkin' => 'Pasien '.$no.' sudah check in.',
            'panggil' => 'Memanggil antrean '.$no.'.',
            'mulai' => 'Pelayanan antrean '.$no.' dimulai.',
            'selesai' => 'Pelayanan antrean '.$no.' selesai, resep: '.$antrean['jenisresep'].'.',
            'batal' => 'Antrean '.$no.' dibatalkan.',
            default => 'Selesai.',
        };
    }

    /**
     * @param  list<array>  $bpjs
     * @return array{ok: bool, pesan: string, peringatan: ?string, data: ?array, bpjs: list<array>}
     */
    private function hasil(bool $ok, string $pesan, ?array $data = null, array $bpjs = [], ?string $peringatan = null): array
    {
        return ['ok' => $ok, 'pesan' => $pesan, 'peringatan' => $peringatan, 'data' => $data, 'bpjs' => $bpjs];
    }
}
