<?php

namespace Tests\Feature\Antrol;

use Symfony\Component\Yaml\Yaml;

class DaftarAntrianTest extends AntrolTestCase
{
    public function test_halaman_awal_antrol_adalah_daftar_antrian_halaman_pertama(): void
    {
        $this->get('/antrol')
            ->assertOk()
            ->assertSeeInOrder(['Antrian Pendaftaran<br>Onsite BPJS', 'Antrian<br>Farmasi', 'Monitoring<br>Antrean'], false)
            ->assertSee('href="'.route('antrol.pendaftaran.poli-fp').'">Ambil Antrian', false)
            ->assertSee('href="'.route('antrol.farmasi').'">Ambil Antrian', false)
            ->assertSee('href="'.route('antrol.monitoring').'">Buka', false)
            ->assertSee('Daftar selanjutnya')
            ->assertDontSee('Daftar sebelumnya')
            ->assertDontSee('Buka Uji service')
            ->assertDontSee('<a class="tombol-kembali"', false);
    }

    public function test_daftar_antrian_halaman_kedua(): void
    {
        foreach ([2, 9] as $hal) {
            $this->get(route('antrol.beranda', ['hal' => $hal]))
                ->assertOk()
                ->assertSeeInOrder(['Log<br>Bridging', 'Alat<br>Uji', 'Dokumentasi<br>API'], false)
                ->assertSee('href="'.route('antrol.log').'">Buka', false)
                ->assertSee('href="'.route('antrol.alat-uji').'">Buka', false)
                ->assertSee('href="'.route('antrol.api-docs').'">Buka', false)
                ->assertSee('Daftar sebelumnya')
                ->assertDontSee('Daftar selanjutnya');
        }
    }

    public function test_header_tanpa_menu_dengan_label_mode(): void
    {
        config(['antrol.mode_simulasi' => true, 'antrol.sumber_data' => 'bpjs', 'antrol.wsrs.token_mode' => 'vendor']);

        $this->get(route('antrol.beranda'))
            ->assertSee('RSU SERENAPITA')
            ->assertSee('Modul Antrean Online')
            ->assertSee('BPJS Development')
            ->assertDontSee('Token WSRS')
            ->assertDontSee('Sumber data')
            ->assertSee('MODE SIMULASI - DEVELOPMENT')
            ->assertSee('Petugas')
            ->assertDontSee('aria-label="Menu utama"', false)
            ->assertDontSee('Supported by');

        config(['antrol.bpjs.base_url' => 'https://apijkn.bpjs-kesehatan.go.id/antreanrs']);
        $this->get(route('antrol.beranda'))->assertSee('BPJS Production');
    }

    public function test_setiap_halaman_selain_daftar_antrian_punya_tombol_kembali(): void
    {
        $this->palsukanBpjs();

        foreach (['antrol.pendaftaran.poli-fp', 'antrol.pendaftaran.peserta-fp', 'antrol.pendaftaran', 'antrol.farmasi', 'antrol.monitoring', 'antrol.log', 'antrol.alat-uji', 'antrol.poli', 'antrol.data-uji', 'antrol.wsbpjs', 'antrol.api-docs'] as $rute) {
            $this->get(route($rute))
                ->assertOk()
                ->assertSee('<a class="tombol-kembali" href="'.route('antrol.beranda').'">', false);
        }
    }

    public function test_alat_uji_berisi_tautan_poli_data_uji_dan_uji_service(): void
    {
        $this->get(route('antrol.alat-uji'))
            ->assertOk()
            ->assertSee('href="'.route('antrol.poli').'"', false)
            ->assertSee('href="'.route('antrol.data-uji').'"', false)
            ->assertSee('href="'.route('antrol.wsbpjs').'"', false);
    }

    public function test_fitur_yang_dihapus_tidak_lagi_tersedia(): void
    {
        $this->get('/antrol/simulator-mjkn')->assertNotFound();
        $this->get('/antrol/demo-uat/tipe')->assertNotFound();
        $this->get('/antrol/demo-uat')->assertNotFound();
    }

    public function test_dokumentasi_api_memuat_swagger_ui_dan_unduhan_yaml(): void
    {
        $this->get(route('antrol.api-docs'))
            ->assertOk()
            ->assertSee('https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui-bundle.js', false)
            ->assertSee('href="'.route('antrol.api-docs.yaml').'" download="openapi.yaml"', false);

        $respons = $this->get(route('antrol.api-docs.yaml'))->assertOk();
        $this->assertStringContainsString('application/yaml', (string) $respons->headers->get('Content-Type'));
        $this->assertSame('3.0.3', Yaml::parse(file_get_contents(base_path('docs/openapi.yaml')))['openapi']);
    }
}
