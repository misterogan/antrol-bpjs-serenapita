<?php

namespace Tests\Feature\Antrol;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Dasar test modul antrol: kredensial palsu dan respons BPJS palsu (tidak terenkripsi,
 * karena AntrolClient meneruskan field "response" yang bukan string apa adanya).
 */
abstract class AntrolTestCase extends TestCase
{
    use RefreshDatabase;

    protected const BASE = 'https://bpjs.test/antreanrs_dev';

    /** Kode poli yang dianggap poli fingerprint oleh Referensi Poli Fingerprint palsu. */
    protected const POLI_FP = 'JAN';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'antrol.bpjs.base_url' => self::BASE,
            'antrol.bpjs.cons_id' => 'uji',
            'antrol.bpjs.secret' => 'rahasia-uji',
            'antrol.bpjs.user_key' => 'kunci-uji',
            'antrol.wsrs.username' => 'wsrs-uji',
            'antrol.wsrs.password' => 'sandi-uji',
            'antrol.mode_simulasi' => false,
            // Test lama memakai alat simulasi; AlatSimulasiTest menguji kondisi disembunyikan.
            'antrol.tampilkan_alat_simulasi' => true,
            // Test lama menguji fitur farmasi; FiturFarmasiNonaktifTest menguji kondisi disembunyikan.
            'antrol.fitur_farmasi' => true,
            // Test tidak bergantung pada .env lokal; test mode vendor menyalakannya sendiri.
            'antrol.wsrs.token_mode' => 'lokal',
            'antrol.wsrs.vendor.base_url' => null,
            // Test lama memakai WSRS lokal & nomor farmasi lokal; test vendor menyalakannya sendiri.
            'antrol.wsrs.provider' => 'lokal',
            'antrol.nomor_farmasi_dari' => 'lokal',
            'antrol.sumber_data' => 'lokal',
            'antrol.kirim_task_farmasi' => true,
            'antrol.internal.api_key' => null,
            'antrol.internal.ip_allowlist' => '',
        ]);
    }

    /**
     * Respons BPJS palsu. $tambahan dapat menimpa pola URL tertentu.
     *
     * @param  array<string, mixed>  $tambahan
     */
    protected function palsukanBpjs(array $tambahan = []): void
    {
        Http::preventStrayRequests();
        Http::fake($tambahan + [
            // Bentuk sesuai log BPJS development: code 1, response berupa daftar langsung.
            self::BASE.'/ref/poli/fp' => Http::response([
                'response' => [['kodesubspesialis' => self::POLI_FP, 'namasubspesialis' => 'Jantung', 'kodepoli' => self::POLI_FP, 'namapoli' => 'JANTUNG']],
                'metadata' => ['code' => 1, 'message' => 'OK'],
            ]),
            self::BASE.'/ref/pasien/fp/*' => Http::response([
                'response' => ['nomorkartu' => '0001234567890', 'daftarfp' => 1],
                'metadata' => ['message' => 'Ok', 'code' => 200],
            ]),
            self::BASE.'/antrean/farmasi/add' => Http::response(['metadata' => ['message' => 'Ok', 'code' => 200]]),
            self::BASE.'/antrean/add' => Http::response(['metadata' => ['message' => 'Ok', 'code' => 200]]),
            self::BASE.'/antrean/updatewaktu' => Http::response(['metadata' => ['message' => 'Ok', 'code' => 200]]),
            self::BASE.'/antrean/pendaftaran/*' => Http::response([
                'response' => [['kodebooking' => 'X1', 'kodepoli' => 'INT', 'status' => 'Belum dilayani']],
                'metadata' => ['message' => 'Ok', 'code' => 200],
            ]),
        ]);
    }
}
