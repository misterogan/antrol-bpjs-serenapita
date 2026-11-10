<?php

namespace Tests\Feature\Antrol;

use App\Models\Antrol\AntreanFarmasi;
use App\Models\Antrol\AntrolLog;
use App\Models\Antrol\Resep;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/** API internal untuk SIMRS vendor (/jkn/internal/*). */
class InternalApiTest extends AntrolTestCase
{
    private const KUNCI = 'kunci-api-uji-0123456789';

    protected function setUp(): void
    {
        parent::setUp();
        config(['antrol.internal.api_key' => self::KUNCI]);
        $this->palsukanBpjs();
    }

    public function test_kunci_api_salah_atau_kosong_ditolak_401(): void
    {
        $this->getJson('/jkn/internal/antrean-farmasi')
            ->assertStatus(401)->assertExactJson(['metadata' => ['message' => 'Kunci API tidak valid', 'code' => 401]]);
        $this->getJson('/jkn/internal/antrean-farmasi', ['x-api-key' => 'salah'])->assertStatus(401);

        config(['antrol.internal.api_key' => '']);
        $this->getJson('/jkn/internal/antrean-farmasi', ['x-api-key' => ''])
            ->assertStatus(401)->assertJsonPath('metadata.message', 'API internal belum dikonfigurasi (ANTROL_INTERNAL_API_KEY kosong)');
    }

    public function test_ip_tidak_diizinkan_ditolak_403(): void
    {
        config(['antrol.internal.ip_allowlist' => '10.0.0.5, 10.0.0.6']);
        $this->api('GET', '/jkn/internal/antrean-farmasi')->assertStatus(403)->assertExactJson(['metadata' => ['message' => 'Alamat IP tidak diizinkan', 'code' => 403]]);

        config(['antrol.internal.ip_allowlist' => '10.0.0.5,127.0.0.1']);
        $this->api('GET', '/jkn/internal/antrean-farmasi')->assertOk();
    }

    public function test_rate_limit_per_ip(): void
    {
        config(['antrol.internal.per_menit' => 2]);

        $this->api('GET', '/jkn/internal/antrean-farmasi')->assertOk();
        $this->api('GET', '/jkn/internal/antrean-farmasi')->assertOk();
        $this->api('GET', '/jkn/internal/antrean-farmasi')->assertStatus(429)->assertJsonPath('metadata.code', 429);
    }

    public function test_kirim_resep_dan_kiriman_ulang_memperbarui(): void
    {
        $body = ['kodebooking' => '20261107INT001', 'jenisresep' => 'non racikan', 'norekammedis' => '000101', 'namapasien' => 'Budi Santoso', 'nomorkartu' => '0001234567890', 'kodepoli' => 'int', 'tanggal' => now()->toDateString()];

        $this->api('POST', '/jkn/internal/resep', $body)
            ->assertOk()
            ->assertJsonPath('response.jenisresep', 'Non Racikan')
            ->assertJsonPath('response.namapasien', 'BUDI SANTOSO')
            ->assertJsonPath('response.sumber', 'vendor')
            ->assertJsonPath('metadata.code', 200);

        $this->api('POST', '/jkn/internal/resep', ['kodebooking' => '20261107INT001', 'jenisresep' => 'Racikan'])->assertOk();
        $this->assertSame(1, Resep::count());
        $this->assertSame('Racikan', Resep::sole()->jenisresep);
    }

    public function test_validasi_resep_dijawab_201(): void
    {
        $this->api('POST', '/jkn/internal/resep', ['jenisresep' => 'Racikan'])
            ->assertStatus(201)->assertExactJson(['metadata' => ['message' => 'Kodebooking wajib diisi.', 'code' => 201]]);
        $this->api('POST', '/jkn/internal/resep', ['kodebooking' => 'KB1', 'jenisresep' => 'Puyer'])
            ->assertStatus(201)->assertJsonPath('metadata.message', 'jenisresep harus Racikan, Non Racikan, atau Tidak ada');
        $this->api('POST', '/jkn/internal/resep', ['kodebooking' => 'KB1', 'jenisresep' => 'Racikan', 'tanggal' => '07-11-2026'])
            ->assertStatus(201)->assertJsonPath('metadata.message', 'Tanggal harus berformat Y-m-d.');
        $this->assertSame(0, Resep::count());
    }

    public function test_batal_resep_membatalkan_antrean_yang_masih_menunggu(): void
    {
        $this->api('POST', '/jkn/internal/resep/TIDAKADA/batal')
            ->assertStatus(201)->assertJsonPath('metadata.message', 'Resep dengan kode booking tersebut tidak ditemukan');

        Resep::factory()->create(['kodebooking' => 'KB-MENUNGGU']);
        $menunggu = AntreanFarmasi::factory()->create(['kodebooking' => 'KB-MENUNGGU', 'nomorantrean' => 3]);
        $this->api('POST', '/jkn/internal/resep/KB-MENUNGGU/batal')
            ->assertOk()
            ->assertJsonPath('response.resep.dibatalkan', true)
            ->assertJsonPath('response.antrean_farmasi', ['nomorantrean' => 3, 'status' => 'batal']);
        $this->assertSame(AntreanFarmasi::BATAL, $menunggu->fresh()->status);

        Resep::factory()->create(['kodebooking' => 'KB-DIPANGGIL']);
        $dipanggil = AntreanFarmasi::factory()->status(AntreanFarmasi::DIPANGGIL)->create(['kodebooking' => 'KB-DIPANGGIL', 'nomorantrean' => 4]);
        $this->api('POST', '/jkn/internal/resep/KB-DIPANGGIL/batal')
            ->assertStatus(201)->assertJsonPath('metadata.message', 'Resep dibatalkan, tetapi antrean farmasi nomor 4 sudah berstatus dipanggil sehingga tidak diubah');
        $this->assertSame(AntreanFarmasi::DIPANGGIL, $dipanggil->fresh()->status);
        $this->assertNotNull(Resep::where('kodebooking', 'KB-DIPANGGIL')->value('dibatalkan_at'));
    }

    public function test_poli_fingerprint_daftar_dan_satu_poli(): void
    {
        $this->api('GET', '/jkn/internal/poli-fingerprint')->assertOk()->assertJsonPath('response', ['list' => [self::POLI_FP], 'jumlah' => 1]);
        $this->api('GET', '/jkn/internal/poli-fingerprint/jan')
            ->assertOk()
            ->assertJsonPath('response.kodepoli', 'JAN')
            ->assertJsonPath('response.wajib_fingerprint', true)
            ->assertJsonPath('response.pesan', config('antrol.pesan_fingerprint'));
        $this->api('GET', '/jkn/internal/poli-fingerprint/ANA')->assertOk()->assertJsonPath('response.wajib_fingerprint', false);

        // Dari cache: BPJS hanya dipanggil sekali.
        Http::assertSentCount(1);
    }

    public function test_poli_fingerprint_gagal_dibaca_dijawab_201_bukan_false(): void
    {
        Http::swap(new Factory);
        $this->palsukanBpjs([self::BASE.'/ref/poli/fp' => Http::failedConnection('cURL error 28: Operation timed out')]);
        Cache::flush();

        $this->api('GET', '/jkn/internal/poli-fingerprint/JAN')
            ->assertStatus(201)
            ->assertJsonMissingPath('response.wajib_fingerprint')
            ->assertJsonPath('metadata.message', 'Referensi Poli Fingerprint dari BPJS gagal dibaca, status fingerprint poli tidak dapat dipastikan');
        $this->api('GET', '/jkn/internal/poli-fingerprint')->assertStatus(201);
    }

    public function test_peserta_fingerprint_meneruskan_jawaban_bpjs(): void
    {
        $this->api('GET', '/jkn/internal/peserta-fingerprint?jenis=noka&nomor=0001234567890')
            ->assertOk()
            ->assertJsonPath('response.metadata_bpjs.code', 200)
            ->assertJsonPath('response.peserta.daftarfp', 1);

        $this->api('GET', '/jkn/internal/peserta-fingerprint?jenis=nik&nomor=123')
            ->assertStatus(201)->assertJsonPath('metadata.message', 'NIK harus 16 digit angka');
    }

    public function test_peserta_fingerprint_bpjs_tidak_bisa_dihubungi(): void
    {
        Http::swap(new Factory);
        $this->palsukanBpjs([self::BASE.'/ref/pasien/fp/*' => Http::failedConnection('cURL error 6: Could not resolve host')]);

        $this->api('GET', '/jkn/internal/peserta-fingerprint?jenis=noka&nomor=0001234567890')
            ->assertStatus(201)
            ->assertJsonPath('metadata.message', 'Referensi Pasien Fingerprint dari BPJS tidak dapat dibaca: cURL error 6: Could not resolve host');
    }

    public function test_daftar_dan_detail_antrean_farmasi(): void
    {
        AntreanFarmasi::factory()->create(['kodebooking' => 'KB-A', 'nomorantrean' => 1, 'nama' => 'PASIEN A', 'norm' => '000001', 'sumber' => AntreanFarmasi::SUMBER_WSRS]);
        AntreanFarmasi::factory()->status(AntreanFarmasi::BATAL)->create(['kodebooking' => 'KB-B', 'nomorantrean' => 2]);

        $this->api('GET', '/jkn/internal/antrean-farmasi')
            ->assertOk()
            ->assertJsonPath('response.tanggal', now()->toDateString())
            ->assertJsonPath('response.ringkasan', ['totalantrean' => 1, 'sisaantrean' => 1, 'antreanpanggil' => 0, 'selesai' => 0, 'batal' => 1])
            ->assertJsonPath('response.list.0.namapasien', 'PASIEN A')
            ->assertJsonPath('response.list.0.sumber', 'mobile_jkn')
            ->assertJsonPath('response.list.0.terkirim_bpjs', null)
            ->assertJsonPath('response.list.1.status', 'batal');

        $this->api('GET', '/jkn/internal/antrean-farmasi/KB-A')->assertOk()->assertJsonPath('response.norekammedis', '000001');
        $this->api('GET', '/jkn/internal/antrean-farmasi/TIDAKADA')
            ->assertStatus(201)->assertJsonPath('metadata.message', 'Antrean farmasi untuk kode booking tersebut tidak ditemukan');
        $this->api('GET', '/jkn/internal/antrean-farmasi?tanggal=kemarin')->assertStatus(201);
    }

    public function test_dicatat_di_log_internal_tanpa_kunci_api(): void
    {
        $this->api('POST', '/jkn/internal/resep', ['kodebooking' => 'KB1', 'jenisresep' => 'Racikan']);
        $this->getJson('/jkn/internal/antrean-farmasi', ['x-api-key' => 'kunci-salah-xyz']);

        $log = AntrolLog::where('arah', 'internal')->orderBy('id')->get();
        $this->assertSame(['POST jkn/internal/resep', 'GET jkn/internal/antrean-farmasi'], $log->pluck('endpoint')->all());
        $this->assertSame([200, 401], $log->pluck('http_status')->all());
        $semua = json_encode(AntrolLog::all()->toArray());
        $this->assertStringNotContainsString(self::KUNCI, $semua);
        $this->assertStringNotContainsString('kunci-salah-xyz', $semua);

        $this->get(route('antrol.log', ['arah' => 'internal']))->assertOk()->assertSee('POST jkn/internal/resep')->assertDontSee(self::KUNCI);
    }

    /** @param  array<string, mixed>  $body */
    private function api(string $method, string $uri, array $body = []): TestResponse
    {
        return $this->json($method, $uri, $body, ['x-api-key' => self::KUNCI]);
    }
}
