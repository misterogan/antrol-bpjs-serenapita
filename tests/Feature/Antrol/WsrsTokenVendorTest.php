<?php

namespace Tests\Feature\Antrol;

use App\Models\Antrol\AntreanFarmasi;
use App\Models\Antrol\AntreanPoliUji;
use App\Models\Antrol\AntrolLog;
use App\Providers\AntrolServiceProvider;
use App\Services\Antrol\VendorTokenValidator;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use ReflectionProperty;

/**
 * Mode token vendor, memakai pola di config/antrol.php dan bentuk jawaban asli POST /jkn/sisa-antrian.
 */
class WsrsTokenVendorTest extends AntrolTestCase
{
    private const VENDOR = 'http://vendor.lan';

    /** Token bukan JWT: pemeriksaan exp dilewati, langsung ke vendor. */
    private const TOKEN = 'token-vendor-rahasia-1234567890';

    private const CEK = self::VENDOR.'/jkn/sisa-antrian';

    /** Jawaban asli vendor untuk token sah (kode booking CEKTOKEN tidak ada). */
    private const JAWABAN_SAH = ['response' => ['title' => 'Proses Gagal!', 'text' => 'Antrean Tidak Ditemukan'], 'metadata' => ['message' => 'Antrean Tidak Ditemukan', 'code' => 201]];

    /** Jawaban asli vendor untuk token tidak valid. */
    private const JAWABAN_UNAUTHORIZED = ['response' => ['message' => 'Unauthorized!', 'status' => 401], 'metadata' => ['message' => 'Unauthorized!', 'code' => 401]];

    private const DITOLAK = ['metadata' => ['message' => 'Token tidak dapat divalidasi', 'code' => 201]];

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();

        config([
            'antrol.wsrs.token_mode' => 'vendor',
            'antrol.wsrs.vendor.base_url' => self::VENDOR,
        ]);
    }

    public function test_pola_config_sesuai_jawaban_asli_vendor(): void
    {
        $this->assertSame([
            'expired' => ['code' => [401], 'pesan' => ['Unauthorized']],
            'tidak_valid' => ['code' => [401], 'pesan' => ['Unauthorized']],
            'sah' => ['code' => [201], 'pesan' => ['Antrean Tidak Ditemukan']],
        ], config('antrol.wsrs.vendor.pola'));
    }

    public function test_token_sah_meneruskan_request_dengan_respons_yang_sama(): void
    {
        $this->vendorSah();
        $poli = AntreanPoliUji::factory()->selesai('Racikan')->create();

        $this->ambil($poli->kodebooking)
            ->assertOk()
            ->assertExactJson([
                'response' => ['jenisresep' => 'Racikan', 'nomorantrean' => 1, 'keterangan' => ''],
                'metadata' => ['message' => 'Ok', 'code' => 200],
            ]);

        Http::assertSent(fn (Request $r) => $r->url() === self::CEK
            && $r->method() === 'POST'
            && $r->header('x-token') === [self::TOKEN]
            && $r->header('x-username') === ['bpjs-uji']
            && $r->data() === ['kodebooking' => 'CEKTOKEN']);
        $this->assertSame(AntreanFarmasi::SUMBER_WSRS, AntreanFarmasi::first()->sumber);
    }

    public function test_status_antrean_farmasi_juga_memakai_token_vendor(): void
    {
        $this->vendorSah();

        $this->postJson('/jkn/status-antrean-farmasi', ['kodebooking' => 'TIDAKADA'], $this->header())
            ->assertExactJson(['metadata' => ['message' => 'kodebooking Tidak Ditemukan', 'code' => 201]]);
    }

    public function test_jawaban_unauthorized_vendor_dijawab_token_tidak_valid(): void
    {
        Http::fake([self::CEK => Http::response(self::JAWABAN_UNAUTHORIZED, 401)]);
        $poli = AntreanPoliUji::factory()->selesai()->create();

        // Pola expired & tidak_valid sama (401 Unauthorized): hasilnya tidak valid.
        $this->ambil($poli->kodebooking)
            ->assertStatus(201)
            ->assertExactJson(['metadata' => ['message' => 'Token Tidak Valid', 'code' => 201]]);
        $this->assertSame(0, AntreanFarmasi::count());
    }

    public function test_pola_expired_yang_berbeda_dijawab_token_expired(): void
    {
        config(['antrol.wsrs.vendor.pola.expired' => ['code' => [401], 'pesan' => ['Token Expired']]]);
        Http::fake([self::CEK => Http::response(['metadata' => ['message' => 'Token Expired', 'code' => 401]], 401)]);

        $this->ambil('X')
            ->assertStatus(201)
            ->assertExactJson(['metadata' => ['message' => 'Token Expired', 'code' => 201]]);
    }

    public function test_jwt_exp_sudah_lewat_ditolak_tanpa_memanggil_vendor(): void
    {
        Http::fake();
        $poli = AntreanPoliUji::factory()->selesai()->create();

        $this->ambil($poli->kodebooking, $this->jwt(['sub' => 'bpjs', 'exp' => now()->subMinute()->getTimestamp()]))
            ->assertStatus(201)
            ->assertExactJson(['metadata' => ['message' => 'Token Expired', 'code' => 201]]);

        Http::assertNothingSent();
        $this->assertSame(0, AntreanFarmasi::count());
    }

    public function test_jwt_exp_masih_berlaku_tetap_diperiksa_ke_vendor_dengan_header_apa_adanya(): void
    {
        $this->vendorSah();
        $token = $this->jwt(['exp' => now()->addHour()->getTimestamp()]);

        $this->ambil('X', $token)->assertExactJson(['metadata' => ['message' => 'Kode Booking tidak ditemukan', 'code' => 201]]);

        Http::assertSent(fn (Request $r) => $r->header('x-token') === [$token] && str_starts_with($token, 'Bearer '));
    }

    public function test_exp_hanya_menolak_tidak_pernah_meloloskan_token(): void
    {
        Http::fake([self::CEK => Http::response(self::JAWABAN_UNAUTHORIZED, 401)]);

        // exp masih jauh, tanda tangan palsu: keputusan tetap di vendor.
        $this->ambil('X', $this->jwt(['exp' => now()->addDay()->getTimestamp()]))
            ->assertExactJson(['metadata' => ['message' => 'Token Tidak Valid', 'code' => 201]]);
        Http::assertSentCount(1);
    }

    public function test_jwt_tanpa_exp_diperiksa_ke_vendor(): void
    {
        $this->vendorSah();

        $this->ambil('X', $this->jwt(['sub' => 'bpjs']))->assertJsonPath('metadata.message', 'Kode Booking tidak ditemukan');

        Http::assertSentCount(1);
    }

    public function test_token_bukan_jwt_atau_payload_rusak_diperiksa_ke_vendor(): void
    {
        $this->vendorSah();
        $validator = app(VendorTokenValidator::class);

        foreach (['token-biasa-tanpa-titik', 'Bearer abc.%%%rusak%%%.def', 'Bearer aGVsbG8.bm90LWpzb24.c2ln', 'Bearer a.b.c.d'] as $token) {
            $this->assertNull($validator->expJwt($token), $token);
            $this->ambil('X', $token)->assertJsonPath('metadata.message', 'Kode Booking tidak ditemukan');
        }

        Http::assertSentCount(4);
    }

    public function test_exp_dibaca_dengan_atau_tanpa_awalan_bearer(): void
    {
        $validator = app(VendorTokenValidator::class);
        $token = $this->jwt(['exp' => 1893456000]);

        $this->assertSame(1893456000, $validator->expJwt($token));
        $this->assertSame(1893456000, $validator->expJwt(substr($token, strlen('Bearer '))));
        $this->assertNull($validator->expJwt($this->jwt(['exp' => 'besok'])));
    }

    public function test_vendor_timeout_atau_tidak_dapat_dihubungi_ditolak(): void
    {
        $this->assertSame(5, config('antrol.wsrs.vendor.timeout'));
        Http::fake([self::CEK => Http::failedConnection('cURL error 28: Operation timed out after 5000 milliseconds')]);

        $this->ambil('X')->assertStatus(201)->assertExactJson(self::DITOLAK);
    }

    public function test_vendor_error_server_atau_bukan_json_ditolak(): void
    {
        foreach ([Http::response(self::JAWABAN_SAH, 500), Http::response('<html>Bad Gateway</html>', 200)] as $jawaban) {
            Cache::flush();
            Http::swap(new Factory);
            Http::preventStrayRequests();
            Http::fake([self::CEK => $jawaban]);

            $this->ambil('X')->assertExactJson(self::DITOLAK);
            Http::assertSentCount(1);
        }

        $alasan = AntrolLog::where('arah', 'vendor')->orderBy('id')->get()->map(fn (AntrolLog $l) => $l->response['alasan'])->all();
        $this->assertSame(['SIMRS menjawab HTTP 500.', 'Jawaban SIMRS tidak dikenali (tanpa metadata.code / metadata.message).'], $alasan);
    }

    public function test_jawaban_tanpa_metadata_tidak_dikenali_dan_ditolak(): void
    {
        Http::fake([self::CEK => Http::response(['status' => 'ok'])]);

        $this->ambil('X')->assertExactJson(self::DITOLAK);
        $this->assertFalse(Cache::has(app(VendorTokenValidator::class)->kunciCache('bpjs-uji', self::TOKEN)));
    }

    public function test_jawaban_di_luar_pola_sah_ditolak(): void
    {
        foreach ([['Token kadaluarsa', 201], ['Antrean Ditemukan', 200], ['Antrean Tidak Ditemukan', 200]] as [$pesan, $code]) {
            Cache::flush();
            Http::swap(new Factory);
            Http::preventStrayRequests();
            Http::fake([self::CEK => Http::response(['metadata' => ['message' => $pesan, 'code' => $code]])]);

            $this->ambil('X')->assertExactJson(self::DITOLAK);
        }
    }

    public function test_pola_belum_diisi_semua_token_ditolak_tanpa_memanggil_vendor(): void
    {
        config(['antrol.wsrs.vendor.pola' => [
            'expired' => ['code' => [], 'pesan' => []],
            'tidak_valid' => ['code' => [401], 'pesan' => []],
            'sah' => ['code' => [], 'pesan' => []],
        ]]);
        Http::fake();

        $this->ambil('X')->assertExactJson(self::DITOLAK);

        Http::assertNothingSent();
        $this->assertStringContainsString('Pola expired & tidak_valid', AntrolLog::where('arah', 'vendor')->first()->response['alasan']);
    }

    public function test_url_vendor_belum_diisi_ditolak(): void
    {
        config(['antrol.wsrs.vendor.base_url' => null]);
        Http::fake();

        $this->ambil('X')->assertExactJson(self::DITOLAK);
        Http::assertNothingSent();
    }

    public function test_token_sah_di_cache_sebagai_hash_selama_dua_menit(): void
    {
        $this->assertSame(2, config('antrol.wsrs.vendor.token_cache_menit'));
        $this->vendorSah();

        $this->ambil('X');
        $this->ambil('X');
        Http::assertSentCount(1);

        $kunci = app(VendorTokenValidator::class)->kunciCache('bpjs-uji', self::TOKEN);
        $this->assertTrue(Cache::has($kunci));
        $this->assertStringNotContainsString(self::TOKEN, json_encode($this->isiCache()));

        $this->travel(121)->seconds();
        $this->ambil('X');
        Http::assertSentCount(2);
    }

    public function test_masa_cache_tidak_melewati_exp_token(): void
    {
        $this->vendorSah();
        $exp = now()->addSeconds(45)->getTimestamp();
        $token = $this->jwt(['exp' => $exp]);

        $this->ambil('X', $token);

        $kunci = app(VendorTokenValidator::class)->kunciCache('bpjs-uji', $token);
        $this->assertLessThanOrEqual($exp, (int) $this->isiCache()[$kunci]['expiresAt']);

        // Setelah exp lewat: ditolak tanpa memanggil vendor lagi, walaupun belum 2 menit.
        $this->travel(46)->seconds();
        $this->ambil('X', $token)->assertExactJson(['metadata' => ['message' => 'Token Expired', 'code' => 201]]);
        Http::assertSentCount(1);
    }

    public function test_token_yang_ditolak_tidak_di_cache(): void
    {
        Http::fake([self::CEK => Http::response(self::JAWABAN_UNAUTHORIZED, 401)]);

        $this->ambil('X');
        $this->ambil('X');

        Http::assertSentCount(2);
    }

    public function test_pemeriksaan_vendor_dicatat_tanpa_token_utuh(): void
    {
        $this->vendorSah();

        $this->ambil('X');

        $log = AntrolLog::where('arah', 'vendor')->sole();
        $this->assertSame('POST /jkn/sisa-antrian', $log->endpoint);
        $this->assertSame('toke...90', $log->request['headers']['x-token']);
        $this->assertSame('sah', $log->response['hasil']);
        $this->assertSame(['message' => 'Antrean Tidak Ditemukan', 'code' => 201], $log->response['metadata']);
        $this->assertStringNotContainsString(self::TOKEN, json_encode(AntrolLog::all()->toArray()));

        $this->get(route('antrol.log', ['arah' => 'vendor']))
            ->assertOk()
            ->assertSee('token sah')
            ->assertDontSee(self::TOKEN);
    }

    public function test_route_auth_modul_menjawab_404_di_mode_vendor(): void
    {
        $this->getJson('/jkn/auth', ['x-username' => 'wsrs-uji', 'x-password' => 'sandi-uji'])->assertNotFound();
        $this->get('/jkn/auth')->assertNotFound()->assertHeader('Content-Type', 'application/json');

        config(['antrol.wsrs.token_mode' => 'lokal']);
        $this->getJson('/jkn/auth', ['x-username' => 'wsrs-uji', 'x-password' => 'sandi-uji'])->assertOk()->assertJsonPath('metadata.code', 200);
    }

    public function test_mode_token_tidak_dikenal_ditolak(): void
    {
        config(['antrol.wsrs.token_mode' => 'campur']);

        $this->expectException(InvalidArgumentException::class);
        AntrolServiceProvider::modeTokenWsrs();
    }

    private function vendorSah(): void
    {
        Http::fake([self::CEK => Http::response(self::JAWABAN_SAH, 201)]);
    }

    private function ambil(string $kodebooking, string $token = self::TOKEN): TestResponse
    {
        return $this->postJson('/jkn/ambil-antrean-farmasi', ['kodebooking' => $kodebooking], $this->header($token));
    }

    /** @return array<string, string> */
    private function header(string $token = self::TOKEN): array
    {
        return ['x-username' => 'bpjs-uji', 'x-token' => $token];
    }

    /**
     * Token JWT HS256 berawalan "Bearer " dengan tanda tangan palsu (tidak diverifikasi modul).
     *
     * @param  array<string, mixed>  $payload
     */
    private function jwt(array $payload): string
    {
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');

        return 'Bearer '.$b64(json_encode(['typ' => 'JWT', 'alg' => 'HS256'])).'.'.$b64(json_encode($payload)).'.'.$b64('tanda-tangan-palsu');
    }

    /** @return array<string, array{value: mixed, expiresAt: float|int}> */
    private function isiCache(): array
    {
        return (new ReflectionProperty(Cache::getStore(), 'storage'))->getValue(Cache::getStore());
    }
}
