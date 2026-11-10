<?php

use App\Http\Controllers\Antrol\ApiDocsController;
use App\Http\Controllers\Antrol\DaftarAntrianController;
use App\Http\Controllers\Antrol\DataUjiController;
use App\Http\Controllers\Antrol\FarmasiController;
use App\Http\Controllers\Antrol\LogController;
use App\Http\Controllers\Antrol\MonitoringController;
use App\Http\Controllers\Antrol\PendaftaranController;
use App\Http\Controllers\Antrol\PendaftaranFingerprintController;
use App\Http\Controllers\Antrol\PoliController;
use App\Http\Controllers\Antrol\WsbpjsController;
use App\Http\Middleware\AntrolAlatSimulasi;
use App\Http\Middleware\AntrolFiturFarmasi;
use Illuminate\Support\Facades\Route;

// Tambahkan middleware login (mis. 'auth') sesuai aplikasi Anda.
Route::prefix('antrol')->name('antrol.')->middleware([])->group(function () {
    // Halaman awal: Daftar Antrian (semua halaman dipilih dari kartu di sini)
    Route::get('/', [DaftarAntrianController::class, 'index'])->name('beranda');
    Route::get('alat-uji', [DaftarAntrianController::class, 'alatUji'])->name('alat-uji');

    // Antrian Pendaftaran Onsite BPJS: tab A Cek Poli Fingerprint, tab B Cek Peserta Fingerprint, tab C Pendaftaran (simulasi)
    Route::get('pendaftaran/poli-fingerprint', [PendaftaranFingerprintController::class, 'poli'])->name('pendaftaran.poli-fp');
    Route::post('pendaftaran/poli-fingerprint', [PendaftaranFingerprintController::class, 'cekPoli'])->name('pendaftaran.poli-fp.cek');
    Route::get('pendaftaran/peserta-fingerprint', [PendaftaranFingerprintController::class, 'peserta'])->name('pendaftaran.peserta-fp');
    Route::post('pendaftaran/peserta-fingerprint', [PendaftaranFingerprintController::class, 'cekPeserta'])->name('pendaftaran.peserta-fp.cek');

    // Alat simulasi: Pendaftaran (simulasi), Poli (simulasi), Data uji. 404 jika ANTROL_TAMPILKAN_ALAT_SIMULASI=false.
    Route::middleware(AntrolAlatSimulasi::class)->group(function () {
        Route::get('pendaftaran', [PendaftaranController::class, 'index'])->name('pendaftaran');
        Route::post('pendaftaran/pasien', [PendaftaranController::class, 'simpanPasien'])->name('pendaftaran.pasien');
        Route::post('pendaftaran/periksa', [PendaftaranController::class, 'periksa'])->name('pendaftaran.periksa');
        Route::post('pendaftaran', [PendaftaranController::class, 'simpan'])->name('pendaftaran.simpan');

        Route::get('poli', [PoliController::class, 'index'])->name('poli');
        Route::post('poli/{kodebooking}/kirim-bpjs', [PoliController::class, 'kirimBpjs'])->name('poli.kirim-bpjs');
        Route::post('poli/{kodebooking}/{aksi}', [PoliController::class, 'aksi'])
            ->whereIn('aksi', ['checkin', 'panggil', 'mulai', 'selesai', 'batal'])
            ->name('poli.aksi');

        Route::get('data-uji', [DataUjiController::class, 'index'])->name('data-uji');
        Route::post('data-uji/poli', [DataUjiController::class, 'simpanPoli'])->name('data-uji.poli');
        Route::post('data-uji/poli/{poli}', [DataUjiController::class, 'aksiPoli'])->name('data-uji.poli.aksi');
        Route::post('data-uji/farmasi/{farmasi}', [DataUjiController::class, 'aksiFarmasi'])
            ->middleware(AntrolFiturFarmasi::class)
            ->name('data-uji.farmasi.aksi');
    });

    // Antrian Farmasi (ANTROL_FITUR_FARMASI=false: 404, ditangani SIMRS vendor)
    Route::middleware(AntrolFiturFarmasi::class)->group(function () {
        Route::get('farmasi', [FarmasiController::class, 'index'])->name('farmasi');
        Route::get('farmasi/data', [FarmasiController::class, 'data'])->name('farmasi.data');
        Route::post('farmasi/ambil', [FarmasiController::class, 'ambil'])->name('farmasi.ambil');
        Route::post('farmasi/buat', [FarmasiController::class, 'buat'])->name('farmasi.buat');
        Route::get('farmasi/resep/{kodebooking}', [FarmasiController::class, 'cariResep'])->name('farmasi.resep');
        Route::post('farmasi/{kodebooking}/kirim-ulang', [FarmasiController::class, 'kirimUlang'])->name('farmasi.kirim-ulang');
        Route::post('farmasi/{kodebooking}/cek-status', [FarmasiController::class, 'cekStatus'])->name('farmasi.cek-status');
        Route::post('farmasi/{kodebooking}/kirim-task/{taskid}', [FarmasiController::class, 'kirimTask'])
            ->whereIn('taskid', ['6', '7'])
            ->name('farmasi.kirim-task');
        Route::post('farmasi/{kodebooking}/{aksi}', [FarmasiController::class, 'aksi'])
            ->whereIn('aksi', ['panggil', 'selesai', 'batal'])
            ->name('farmasi.aksi');
    });

    // Monitoring Antrean
    Route::get('monitoring', [MonitoringController::class, 'index'])->name('monitoring');
    Route::post('monitoring/belum-dilayani', [MonitoringController::class, 'belumDilayani'])->name('monitoring.belum-dilayani');
    Route::post('monitoring/belum-dilayani-poli', [MonitoringController::class, 'belumDilayaniPerPoli'])->name('monitoring.belum-dilayani-poli');
    // Sinkron jadwal dokter (HFIS): dipakai pilihan jadwal Monitoring, tetap ada walaupun alat simulasi disembunyikan.
    Route::post('monitoring/sinkron-jadwal', [MonitoringController::class, 'sinkronJadwal'])->name('monitoring.sinkron-jadwal');

    // Log bridging
    Route::get('log', [LogController::class, 'index'])->name('log');

    // Dokumentasi API (Swagger UI)
    Route::get('api-docs', [ApiDocsController::class, 'index'])->name('api-docs');
    Route::get('api-docs/openapi.yaml', [ApiDocsController::class, 'spesifikasi'])->name('api-docs.yaml');

    // Uji service (halaman lama, dari Alat Uji; tetap dipakai untuk capture form UAT)
    Route::get('uji-service', [WsbpjsController::class, 'index'])->name('wsbpjs');
    Route::post('poli-fingerprint', [WsbpjsController::class, 'poliFingerprint'])->name('poli-fp');
    Route::post('pasien-fingerprint', [WsbpjsController::class, 'pasienFingerprint'])->name('pasien-fp');
    Route::post('tambah-farmasi', [WsbpjsController::class, 'tambahFarmasi'])->middleware(AntrolFiturFarmasi::class)->name('tambah-farmasi');
    Route::post('belum-dilayani', [WsbpjsController::class, 'belumDilayani'])->name('belum-dilayani');
    Route::post('antrean-kodebooking', [WsbpjsController::class, 'antreanPerKodeBooking'])->name('antrean-kodebooking');
    Route::post('list-task', [WsbpjsController::class, 'listWaktuTask'])->name('list-task');
    Route::post('belum-dilayani-poli', [WsbpjsController::class, 'belumDilayaniPerPoli'])->name('belum-dilayani-poli');
    Route::post('cek-pendaftaran', [WsbpjsController::class, 'cekPendaftaran'])->name('cek-pendaftaran');

});
