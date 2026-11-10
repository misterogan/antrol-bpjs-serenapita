<?php

namespace Tests\Feature\Antrol;

use App\Models\Antrol\AntrolLog;
use App\Services\Antrol\VendorWsrsClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/** Klien WSRS SIMRS vendor: Ambil & Status Antrean Farmasi. Semua panggilan HTTP dipalsukan. */
class VendorWsrsClientTest extends AntrolTestCase
{
    private const VENDOR = 'https://simrs.test';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config([
            'antrol.wsrs.vendor.base_url' => self::VENDOR,
            'antrol.wsrs.vendor.username' => 'rs-uji',
            'antrol.wsrs.vendor.password' => 'sandi-vendor-rahasia',
        ]);
    }

    public function test_ambil_sukses_dan_token_dipakai_ulang_dari_cache(): void
    {
        $token = $this->jwt(now()->addHour()->getTimestamp());
        Http::fake([
            self::VENDOR.'/jkn/auth' => Http::response(['response' => ['token' => $token], 'metadata' => ['message' => 'Ok', 'code' => 200]]),
            self::VENDOR.'/jkn/ambil-antrean-farmasi' => Http::response([
                'response' => ['jenisresep' => 'Racikan', 'nomorantrean' => 7, 'keterangan' => ''],
                'metadata' => ['message' => 'Ok', 'code' => 200],
            ]),
        ]);

        $client = app(VendorWsrsClient::class);
        $hasil = $client->ambilAntreanFarmasi('KB001');
        $client->ambilAntreanFarmasi('KB002');

        $this->assertTrue($hasil['ok']);
        $this->assertSame(7, $hasil['response']['nomorantrean']);
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $r) => $r->url() === self::VENDOR.'/jkn/auth'
            && $r->hasHeader('x-username', 'rs-uji') && $r->hasHeader('x-password', 'sandi-vendor-rahasia'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/jkn/ambil-antrean-farmasi')
            && $r->hasHeader('x-token', $token) && $r['kodebooking'] === 'KB001');

        // Log modul tanpa password dan token.
        $log = AntrolLog::where('arah', 'vendor')->get()->toJson();
        $this->assertStringNotContainsString('sandi-vendor-rahasia', $log);
        $this->assertStringNotContainsString($token, $log);
        $this->assertSame(3, AntrolLog::where('arah', 'vendor')->count());
    }

    public function test_status_kode_1_dianggap_sukses(): void
    {
        $this->palsukanVendor(['response' => ['jenisresep' => 'Non racikan', 'totalantrean' => 5, 'sisaantrean' => 3, 'antreanpanggil' => 2, 'keterangan' => ''], 'metadata' => ['message' => 'OK', 'code' => 1]]);

        $hasil = app(VendorWsrsClient::class)->statusAntreanFarmasi('KB001');

        $this->assertTrue($hasil['ok']);
        $this->assertSame(3, $hasil['response']['sisaantrean']);
    }

    public function test_kode_selain_200_dan_1_gagal_dengan_pesan_vendor(): void
    {
        $this->palsukanVendor(['metadata' => ['message' => 'Antrean Tidak Ditemukan', 'code' => 201]]);

        $hasil = app(VendorWsrsClient::class)->statusAntreanFarmasi('KB001');

        $this->assertFalse($hasil['ok']);
        $this->assertSame('Antrean Tidak Ditemukan', $hasil['pesan']);
        $this->assertTrue(VendorWsrsClient::kodeSukses('200'));
        $this->assertFalse(VendorWsrsClient::kodeSukses(null));
    }

    public function test_401_refresh_token_sekali_lalu_ulang(): void
    {
        $lama = $this->jwt(now()->addHour()->getTimestamp(), 'lama');
        $baru = $this->jwt(now()->addHour()->getTimestamp(), 'baru');
        Http::fake([
            self::VENDOR.'/jkn/auth' => Http::sequence()
                ->push(['response' => ['token' => $lama], 'metadata' => ['message' => 'Ok', 'code' => 200]])
                ->push(['response' => ['token' => $baru], 'metadata' => ['message' => 'Ok', 'code' => 200]]),
            self::VENDOR.'/jkn/ambil-antrean-farmasi' => Http::sequence()
                ->push(['metadata' => ['message' => 'Unauthorized!', 'code' => 401]], 401)
                ->push(['response' => ['jenisresep' => 'Racikan', 'nomorantrean' => 9, 'keterangan' => ''], 'metadata' => ['message' => 'Ok', 'code' => 200]]),
        ]);

        $hasil = app(VendorWsrsClient::class)->ambilAntreanFarmasi('KB001');

        $this->assertTrue($hasil['ok']);
        $this->assertSame(9, $hasil['response']['nomorantrean']);
        Http::assertSentCount(4);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/jkn/ambil-antrean-farmasi') && $r->hasHeader('x-token', $baru));
    }

    public function test_401_terus_menerus_hanya_diulang_sekali(): void
    {
        Http::fake([
            self::VENDOR.'/jkn/auth' => Http::response(['response' => ['token' => 'tok'], 'metadata' => ['message' => 'Ok', 'code' => 200]]),
            self::VENDOR.'/jkn/ambil-antrean-farmasi' => Http::response(['metadata' => ['message' => 'Unauthorized!', 'code' => 401]], 401),
        ]);

        $hasil = app(VendorWsrsClient::class)->ambilAntreanFarmasi('KB001');

        $this->assertFalse($hasil['ok']);
        $this->assertSame('Unauthorized!', $hasil['pesan']);
        Http::assertSentCount(4);
    }

    public function test_token_di_cache_sampai_exp_dikurangi_satu_menit(): void
    {
        $this->palsukanVendor(['metadata' => ['message' => 'Ok', 'code' => 200]], now()->addSeconds(120)->getTimestamp());
        $client = app(VendorWsrsClient::class);

        $client->statusAntreanFarmasi('KB001');
        $this->travel(50)->seconds();
        $client->statusAntreanFarmasi('KB001');
        $this->assertSame(1, $this->jumlahAuth());

        $this->travel(15)->seconds();
        $client->statusAntreanFarmasi('KB001');
        $this->assertSame(2, $this->jumlahAuth());
    }

    public function test_timeout_10_detik_menjadi_pesan_gagal(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $hasil = app(VendorWsrsClient::class)->ambilAntreanFarmasi('KB001');

        $this->assertFalse($hasil['ok']);
        $this->assertSame('Auth SIMRS vendor gagal: SIMRS vendor tidak dapat dihubungi atau timeout (10 detik).', $hasil['pesan']);
        $this->assertStringContainsString('timed out', (string) $hasil['error']);
        $this->assertSame(10, config('antrol.wsrs.vendor.wsrs_timeout'));
    }

    public function test_base_url_kosong_tidak_memanggil_vendor(): void
    {
        config(['antrol.wsrs.vendor.base_url' => null]);
        Http::fake();

        $hasil = app(VendorWsrsClient::class)->statusAntreanFarmasi('KB001');

        $this->assertFalse($hasil['ok']);
        $this->assertStringContainsString('ANTROL_VENDOR_BASE_URL', $hasil['pesan']);
        Http::assertNothingSent();
    }

    /** @param  array<string, mixed>  $jawaban */
    private function palsukanVendor(array $jawaban, ?int $exp = null): void
    {
        Http::fake([
            self::VENDOR.'/jkn/auth' => Http::response(['response' => ['token' => $this->jwt($exp ?? now()->addHour()->getTimestamp())], 'metadata' => ['message' => 'Ok', 'code' => 200]]),
            self::VENDOR.'/jkn/*' => Http::response($jawaban),
        ]);
    }

    private function jumlahAuth(): int
    {
        return Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/jkn/auth'))->count();
    }

    private function jwt(int $exp, string $sub = 'rs-uji'): string
    {
        $b64 = fn (array $d) => rtrim(strtr(base64_encode((string) json_encode($d)), '+/', '-_'), '=');

        return $b64(['alg' => 'HS256', 'typ' => 'JWT']).'.'.$b64(['sub' => $sub, 'exp' => $exp]).'.tandatangan';
    }
}
