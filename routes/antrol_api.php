<?php

use App\Http\Controllers\Antrol\InternalApiController;
use App\Http\Controllers\Antrol\WsrsController;
use App\Http\Middleware\AntrolBahasa;
use App\Http\Middleware\AntrolFiturFarmasi;
use App\Http\Middleware\AntrolWsrsProvider;
use Illuminate\Support\Facades\Route;

/*
 * WSRS antrean farmasi (dipanggil BPJS / Mobile JKN).
 * Awalan & nama path diatur di config antrol.wsrs (default /jkn/...), didaftarkan oleh AntrolServiceProvider.
 */
$path = fn (string $kunci) => trim((string) config("antrol.wsrs.paths.{$kunci}"), '/');

Route::get($path('auth'), [WsrsController::class, 'token'])->name('wsrs.auth');

// ANTROL_WSRS_PROVIDER=vendor: kedua route menjawab 404 (BPJS memanggil SIMRS vendor), kode tetap sebagai cadangan.
// ANTROL_FITUR_FARMASI=false: route farmasi menjawab 404 (antrean farmasi ditangani SIMRS vendor).
Route::middleware([AntrolFiturFarmasi::class.':json', AntrolWsrsProvider::class, 'wsrs.token'])->group(function () use ($path) {
    Route::post($path('ambil_farmasi'), [WsrsController::class, 'ambilAntreanFarmasi'])->name('wsrs.ambil-farmasi');
    Route::post($path('status_farmasi'), [WsrsController::class, 'statusAntreanFarmasi'])->name('wsrs.status-farmasi');
});

/*
 * API internal untuk SIMRS vendor: /jkn/internal/* (dokumentasi: docs/openapi.yaml).
 * Pengaman: header x-api-key, IP allowlist, dan rate limit (lihat config antrol.internal).
 */
Route::prefix('internal')->name('internal.')
    ->middleware(['throttle:antrol-internal', 'antrol.internal', AntrolBahasa::class])
    ->group(function () {
        Route::get('poli-fingerprint', [InternalApiController::class, 'poliFingerprint'])->name('poli-fp');
        Route::get('poli-fingerprint/{kodepoli}', [InternalApiController::class, 'poliFingerprintSatu'])->name('poli-fp.satu');
        Route::get('peserta-fingerprint', [InternalApiController::class, 'pesertaFingerprint'])->name('peserta-fp');

        Route::middleware(AntrolFiturFarmasi::class.':json')->group(function () {
            Route::post('resep', [InternalApiController::class, 'simpanResep'])->name('resep');
            Route::post('resep/{kodebooking}/batal', [InternalApiController::class, 'batalResep'])->name('resep.batal');
            Route::get('antrean-farmasi', [InternalApiController::class, 'antreanFarmasi'])->name('antrean-farmasi');
            Route::get('antrean-farmasi/{kodebooking}', [InternalApiController::class, 'antreanFarmasiSatu'])->name('antrean-farmasi.satu');
        });
    });
