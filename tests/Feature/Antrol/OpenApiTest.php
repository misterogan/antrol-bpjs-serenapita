<?php

namespace Tests\Feature\Antrol;

use Illuminate\Routing\Route as RouteItem;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * docs/openapi-farmasi.yaml (arsip lengkap) mendokumentasikan semua route /jkn/internal/* serta WSRS untuk BPJS;
 * docs/openapi.yaml (yang tampil) tanpa path farmasi/resep karena antrean farmasi ditangani SIMRS vendor.
 */
class OpenApiTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $spek;

    protected function setUp(): void
    {
        parent::setUp();
        $this->spek = Yaml::parseFile(base_path('docs/openapi-farmasi.yaml'));
    }

    public function test_openapi_utama_tanpa_path_farmasi_dan_resep(): void
    {
        $spek = Yaml::parseFile(base_path('docs/openapi.yaml'));

        $this->assertSame(['/jkn/internal/poli-fingerprint', '/jkn/internal/poli-fingerprint/{kodepoli}', '/jkn/internal/peserta-fingerprint'], array_keys($spek['paths']));
        $this->assertSame(['API Internal untuk Dhealth'], array_column($spek['tags'], 'name'));
        foreach ($spek['paths'] as $path => $operasi) {
            $this->assertSame(['API Internal untuk Dhealth'], $operasi['get']['tags'], $path);
        }
    }

    public function test_spesifikasi_openapi_3_valid_dengan_dua_tag(): void
    {
        $this->assertMatchesRegularExpression('/^3\.0\.\d+$/', $this->spek['openapi']);
        $this->assertNotEmpty($this->spek['info']['title']);
        // Nama tag API internal boleh disesuaikan (mis. nama vendor); tag kedua selalu WSRS untuk BPJS.
        $tag = array_column($this->spek['tags'], 'name');
        $this->assertCount(2, $tag);
        $this->assertSame('WSRS untuk BPJS', $tag[1]);
        $this->assertStringStartsWith('API Internal untuk ', $tag[0]);
        $this->assertSame(['type' => 'apiKey', 'in' => 'header', 'name' => 'x-api-key'], array_intersect_key($this->spek['components']['securitySchemes']['ApiKey'], array_flip(['type', 'in', 'name'])));
        $this->assertSame('x-token', $this->spek['components']['securitySchemes']['WsrsToken']['name']);
        $this->assertSame('x-username', $this->spek['components']['securitySchemes']['WsrsUsername']['name']);
    }

    public function test_deskripsi_untuk_vendor_singkat_dan_tanpa_catatan_internal(): void
    {
        $this->assertSame('API Internal untuk Dhealth', $this->spek['tags'][0]['name']);

        $deskripsi = $this->spek['info']['description'];
        foreach (['SIMRS Dhealth → modul → BPJS', 'POST /jkn/internal/resep', 'x-api-key', 'alamat IP', 'per menit', '| 200 |', '| 201 |', '| 401 |', '[isi kontak RS]'] as $isi) {
            $this->assertStringContainsString($isi, $deskripsi);
        }
        foreach (['disepakati', 'CEKTOKEN', 'sisa-antrian', 'task ID 6', 'ANTROL_'] as $internal) {
            $this->assertStringNotContainsString($internal, $deskripsi);
        }
    }

    public function test_catatan_kesepakatan_vendor_terpisah_dari_swagger(): void
    {
        $catatan = file_get_contents(base_path('docs/catatan-kesepakatan-vendor.md'));

        foreach (['POST /jkn/internal/resep', 'CEKTOKEN', 'Task ID 6 dan 7', 'GET /jkn/internal/poli-fingerprint/{kodepoli}', 'ANTROL_INTERNAL_IP_ALLOWLIST', 'ANTROL_INTERNAL_API_KEY'] as $isi) {
            $this->assertStringContainsString($isi, $catatan);
        }
        $this->assertStringNotContainsString('catatan-kesepakatan', file_get_contents(base_path('docs/openapi.yaml')));
    }

    public function test_semua_route_internal_terdokumentasi(): void
    {
        $internal = collect(Route::getRoutes()->getRoutes())->filter(fn (RouteItem $r) => str_starts_with($r->uri(), 'jkn/internal/'));
        $this->assertCount(7, $internal);

        foreach ($internal as $route) {
            $path = '/'.$route->uri();
            $method = strtolower($route->methods()[0]);
            $this->assertArrayHasKey($path, $this->spek['paths'], "Route {$path} belum ada di docs/openapi.yaml");
            $operasi = $this->spek['paths'][$path][$method] ?? null;
            $this->assertNotNull($operasi, "Method {$method} {$path} belum terdokumentasi");
            $this->assertSame([$this->spek['tags'][0]['name']], $operasi['tags']);
            $this->assertSame([['ApiKey' => []]], $operasi['security']);
            $this->assertNotEmpty($operasi['summary']);
            foreach (['200', '201', '401', '403', '429'] as $kode) {
                $this->assertArrayHasKey($kode, $operasi['responses'], "{$method} {$path} tanpa jawaban {$kode}");
            }
        }
    }

    public function test_wsrs_untuk_bpjs_terdokumentasi_dengan_semua_jawaban_gagal(): void
    {
        $ambil = $this->spek['paths']['/jkn/ambil-antrean-farmasi']['post'];
        $status = $this->spek['paths']['/jkn/status-antrean-farmasi']['post'];

        foreach ([$ambil, $status] as $operasi) {
            $this->assertSame(['WSRS untuk BPJS'], $operasi['tags']);
            $this->assertSame([['WsrsToken' => [], 'WsrsUsername' => []]], $operasi['security']);
        }

        $pesanAmbil = array_map(fn ($c) => $c['value']['metadata']['message'], $ambil['responses']['201']['content']['application/json']['examples']);
        foreach (['Kode Booking tidak ditemukan', 'Pelayanan poli belum selesai, antrean farmasi belum dapat diambil', 'Jenis resep belum tersedia, silakan hubungi farmasi', 'Data antrean BPJS tidak dapat diperiksa, silakan coba lagi', 'Token Expired', 'Token Tidak Valid', 'Token tidak dapat divalidasi'] as $pesan) {
            $this->assertContains($pesan, $pesanAmbil);
        }
    }

    public function test_koleksi_postman_valid(): void
    {
        $vendor = json_decode(file_get_contents(base_path('docs/postman-api-internal-vendor.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['base_url', 'api_key'], array_slice(array_column($vendor['variable'], 'key'), 0, 2));
        $this->assertCount(7, $vendor['item']);

        $wsrs = json_decode(file_get_contents(base_path('postman_wsrs_antrean_farmasi.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('/jkn', $wsrs['variable'][0]['value']);
    }
}
