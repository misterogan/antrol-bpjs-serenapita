<?php

namespace Tests\Feature\Antrol;

use App\Models\Antrol\AntrolLog;
use App\Models\Antrol\JadwalUji;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class MonitoringLogTest extends AntrolTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->palsukanBpjs();
    }

    public function test_monitoring_menampilkan_pilihan_jadwal_dari_data(): void
    {
        JadwalUji::factory()->poli('INT', 'PENYAKIT DALAM')->create(['namadokter' => 'dr. Monitor']);

        $this->get(route('antrol.monitoring'))
            ->assertOk()
            ->assertSee('dr. Monitor')
            ->assertSee('INT|', false);
    }

    public function test_belum_dilayani_semua_ditampilkan_sebagai_tabel(): void
    {
        $this->followingRedirects()
            ->post(route('antrol.monitoring.belum-dilayani'))
            ->assertOk()
            ->assertSee('1</b> antrean belum dilayani', false)
            ->assertSee('Belum dilayani');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/antrean/pendaftaran/aktif'));
    }

    public function test_belum_dilayani_per_poli_memakai_jadwal_terpilih(): void
    {
        $this->post(route('antrol.monitoring.belum-dilayani-poli'), ['hari' => 2, 'jadwal' => 'INT|12345|08:00-12:00'])
            ->assertRedirect(route('antrol.monitoring'));

        Http::assertSent(fn (Request $r) => str_ends_with(
            $r->url(),
            '/antrean/pendaftaran/kodepoli/INT/kodedokter/12345/hari/2/jampraktek/08:00-12:00'
        ));
    }

    public function test_belum_dilayani_per_poli_wajib_jadwal_atau_kode_manual(): void
    {
        $this->post(route('antrol.monitoring.belum-dilayani-poli'), ['hari' => 1])
            ->assertSessionHasErrors(['kodepoli', 'kodedokter', 'jampraktek']);

        Http::assertNothingSent();
    }

    public function test_log_dapat_difilter_berdasarkan_arah(): void
    {
        AntrolLog::catat('wsbpjs', 'GET /ref/poli/fp', null, ['metadata' => ['code' => 200, 'message' => 'Ok']], 200);
        AntrolLog::catat('wsrs', 'POST ambilantreanfarmasi', ['kodebooking' => 'KB-LOG'], ['metadata' => ['code' => 201, 'message' => 'Kode Booking tidak ditemukan']], 201);

        $this->get(route('antrol.log', ['arah' => 'wsrs']))
            ->assertOk()
            ->assertSee('POST ambilantreanfarmasi')
            ->assertSee('KB-LOG')
            ->assertDontSee('GET /ref/poli/fp');

        $this->get(route('antrol.log'))->assertSee('GET /ref/poli/fp')->assertSee('POST ambilantreanfarmasi');
    }

    public function test_halaman_uji_service_lama_tetap_tersedia(): void
    {
        $this->get(route('antrol.wsbpjs'))->assertOk()->assertSee('Service WS BPJS')->assertSee('Tambah Antrean Farmasi');
        $this->get(route('antrol.data-uji'))->assertOk()->assertSee('Data uji WSRS');
        $this->get('/antrol')->assertOk()->assertSee('Daftar Antrian');
    }
}
