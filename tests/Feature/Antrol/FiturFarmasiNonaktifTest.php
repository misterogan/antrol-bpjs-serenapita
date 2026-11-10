<?php

namespace Tests\Feature\Antrol;

use App\Http\Middleware\AntrolFiturFarmasi;

/** ANTROL_FITUR_FARMASI=false: antrean farmasi ditangani SIMRS vendor, menu farmasi disembunyikan dan route-nya 404. */
class FiturFarmasiNonaktifTest extends AntrolTestCase
{
    private const KUNCI = 'kunci-internal-uji';

    protected function setUp(): void
    {
        parent::setUp();

        config(['antrol.fitur_farmasi' => false, 'antrol.internal.api_key' => self::KUNCI]);
    }

    public function test_kartu_dan_tautan_farmasi_tidak_tampil(): void
    {
        $this->get(route('antrol.beranda'))
            ->assertOk()
            ->assertSeeInOrder(['Antrian Pendaftaran<br>Onsite BPJS', 'Monitoring<br>Antrean', 'Log<br>Bridging'], false)
            ->assertDontSee('Antrian<br>Farmasi', false)
            ->assertDontSee('href="'.route('antrol.farmasi').'"', false);

        $this->get(route('antrol.beranda', ['hal' => 2]))
            ->assertOk()
            ->assertSee('Alat<br>Uji', false)
            ->assertSee('Dokumentasi<br>API', false)
            ->assertDontSee('Antrian<br>Farmasi', false);

        $this->get(route('antrol.wsbpjs'))->assertOk()->assertDontSee('Tambah Antrean Farmasi');
        $this->get(route('antrol.data-uji'))->assertOk()->assertDontSee('Antrean farmasi hari ini');
    }

    public function test_route_web_farmasi_404(): void
    {
        $this->get(route('antrol.farmasi'))->assertNotFound();
        $this->get(route('antrol.farmasi.data'))->assertNotFound();
        $this->get(route('antrol.farmasi.resep', 'KB1'))->assertNotFound();
        $this->post(route('antrol.farmasi.ambil'), ['kodebooking' => 'KB1'])->assertNotFound();
        $this->post(route('antrol.farmasi.buat'), ['kodebooking' => 'KB1'])->assertNotFound();
        $this->post(route('antrol.farmasi.kirim-task', ['KB1', 6]))->assertNotFound();
        $this->post(route('antrol.farmasi.kirim-task', ['KB1', 7]))->assertNotFound();
        $this->post(route('antrol.farmasi.kirim-ulang', 'KB1'))->assertNotFound();
        $this->post(route('antrol.farmasi.cek-status', 'KB1'))->assertNotFound();
        foreach (['panggil', 'selesai', 'batal'] as $aksi) {
            $this->post(route('antrol.farmasi.aksi', ['KB1', $aksi]))->assertNotFound();
        }
        $this->post(route('antrol.tambah-farmasi'), ['kodebooking' => 'KB1'])->assertNotFound();
        $this->post('/antrol/data-uji/farmasi/1', ['aksi' => 'panggil'])->assertNotFound();
    }

    public function test_route_wsrs_farmasi_404(): void
    {
        foreach (['/jkn/ambil-antrean-farmasi', '/jkn/status-antrean-farmasi'] as $url) {
            $this->postJson($url, ['kodebooking' => 'X'], ['x-username' => 'wsrs-uji', 'x-token' => 'abc'])
                ->assertNotFound()
                ->assertJsonPath('metadata.code', 404)
                ->assertJsonPath('metadata.message', AntrolFiturFarmasi::PESAN);
        }
    }

    public function test_route_internal_resep_dan_antrean_farmasi_404(): void
    {
        $kunci = ['x-api-key' => self::KUNCI];

        $this->postJson('/jkn/internal/resep', ['kodebooking' => 'X', 'jenisresep' => 'Racikan'], $kunci)->assertNotFound();
        $this->postJson('/jkn/internal/resep/X/batal', [], $kunci)->assertNotFound();
        $this->getJson('/jkn/internal/antrean-farmasi', $kunci)->assertNotFound()->assertJsonPath('metadata.code', 404);
        $this->getJson('/jkn/internal/antrean-farmasi/X', $kunci)->assertNotFound();
    }

    public function test_menu_lain_tetap_200(): void
    {
        foreach (['antrol.beranda', 'antrol.pendaftaran.poli-fp', 'antrol.pendaftaran.peserta-fp', 'antrol.monitoring', 'antrol.log', 'antrol.alat-uji', 'antrol.api-docs', 'antrol.api-docs.yaml', 'antrol.wsbpjs', 'antrol.poli', 'antrol.data-uji'] as $rute) {
            $this->get(route($rute))->assertOk();
        }
    }
}
