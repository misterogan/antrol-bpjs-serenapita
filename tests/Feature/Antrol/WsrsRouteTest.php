<?php

namespace Tests\Feature\Antrol;

use App\Models\Antrol\AntrolLog;
use App\Providers\AntrolServiceProvider;
use Illuminate\Routing\Route as RouteItem;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

class WsrsRouteTest extends AntrolTestCase
{
    public function test_url_wsrs_default_memakai_awalan_jkn(): void
    {
        $this->assertSame(url('/jkn/auth'), route('wsrs.auth'));
        $this->assertSame(url('/jkn/ambil-antrean-farmasi'), route('wsrs.ambil-farmasi'));
        $this->assertSame(url('/jkn/status-antrean-farmasi'), route('wsrs.status-farmasi'));
    }

    public function test_url_lama_api_wsrs_tidak_lagi_tersedia(): void
    {
        $this->getJson('/api/wsrs/auth')->assertNotFound();
        $this->postJson('/api/wsrs/ambilantreanfarmasi', ['kodebooking' => 'X'])->assertNotFound();
        $this->postJson('/api/wsrs/statusantreanfarmasi', ['kodebooking' => 'X'])->assertNotFound();
    }

    public function test_middleware_token_tetap_sama_di_url_baru(): void
    {
        $this->postJson('/jkn/ambil-antrean-farmasi', ['kodebooking' => 'X'])
            ->assertStatus(201)
            ->assertExactJson(['metadata' => ['message' => 'Header x-token dan x-username wajib diisi', 'code' => 201]]);

        $this->postJson('/jkn/status-antrean-farmasi', ['kodebooking' => 'X'], ['x-username' => 'salah', 'x-token' => 'abc'])
            ->assertExactJson(['metadata' => ['message' => 'Username Tidak Sesuai', 'code' => 201]]);

        $this->assertSame('POST jkn/ambil-antrean-farmasi', AntrolLog::orderBy('id')->value('endpoint'));
    }

    public function test_error_di_url_wsrs_tetap_berformat_json_tanpa_header_accept(): void
    {
        $this->get('/jkn/ambil-antrean-farmasi')
            ->assertStatus(405)
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_route_wsrs_tidak_bertabrakan_dengan_route_lain(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes());

        // Di bawah /jkn hanya WSRS untuk BPJS dan API internal untuk SIMRS vendor (/jkn/internal/*).
        $jkn = $routes->filter(fn (RouteItem $r) => str_starts_with($r->uri(), 'jkn/') && ! str_starts_with($r->uri(), 'jkn/internal/'));
        $this->assertEqualsCanonicalizing(['wsrs.auth', 'wsrs.ambil-farmasi', 'wsrs.status-farmasi'], $jkn->map->getName()->values()->all());
        $internal = $routes->filter(fn (RouteItem $r) => str_starts_with($r->uri(), 'jkn/internal/'));
        $this->assertTrue($internal->every(fn (RouteItem $r) => str_starts_with((string) $r->getName(), 'internal.')));

        $antrol = $routes->filter(fn (RouteItem $r) => str_starts_with($r->uri(), 'antrol'));
        $this->assertTrue($antrol->every(fn (RouteItem $r) => str_starts_with((string) $r->getName(), 'antrol.')));

        $kunci = $routes->flatMap(fn (RouteItem $r) => array_map(fn (string $m) => $m.' '.$r->uri(), $r->methods()));
        $this->assertSame($kunci->count(), $kunci->unique()->count(), 'Ada route dengan method dan URL yang sama.');
    }

    public function test_awalan_wsrs_kosong_atau_antrol_ditolak(): void
    {
        foreach (['', '/', 'antrol', 'Antrol/wsrs'] as $prefix) {
            config(['antrol.wsrs.prefix' => $prefix]);
            try {
                AntrolServiceProvider::prefixWsrs();
                $this->fail("Awalan '{$prefix}' seharusnya ditolak.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        config(['antrol.wsrs.prefix' => '/jkn/']);
        $this->assertSame('jkn', AntrolServiceProvider::prefixWsrs());
    }
}
