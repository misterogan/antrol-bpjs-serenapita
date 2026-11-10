<?php

namespace Tests\Feature\Antrol;

use App\Models\Antrol\AntreanPoliUji;
use App\Models\Antrol\JadwalUji;
use App\Models\Antrol\PasienUji;
use App\Services\Antrol\PelayananService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/** ANTROL_TAMPILKAN_ALAT_SIMULASI: alat simulasi disembunyikan (default) atau ditampilkan. */
class AlatSimulasiTest extends AntrolTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->palsukanBpjs();
    }

    public function test_default_config_alat_simulasi_mati(): void
    {
        // Nilai default (tanpa env) harus false; .env lokal bisa berbeda, jadi yang diperiksa sumber config-nya.
        $this->assertStringContainsString("env('ANTROL_TAMPILKAN_ALAT_SIMULASI', false)", file_get_contents(config_path('antrol.php')));
    }

    public function test_route_alat_simulasi_menjawab_404_jika_disembunyikan(): void
    {
        config(['antrol.tampilkan_alat_simulasi' => false]);
        $poli = AntreanPoliUji::factory()->create();

        $this->get(route('antrol.pendaftaran'))->assertNotFound();
        $this->post(route('antrol.pendaftaran.periksa'))->assertNotFound();
        $this->post(route('antrol.pendaftaran.simpan'))->assertNotFound();
        $this->post(route('antrol.pendaftaran.pasien'))->assertNotFound();
        $this->get(route('antrol.poli'))->assertNotFound();
        $this->post(route('antrol.poli.aksi', [$poli->kodebooking, 'checkin']))->assertNotFound();
        $this->get(route('antrol.data-uji'))->assertNotFound();

        $this->assertNull($poli->fresh()->taskid3_at);
    }

    public function test_tab_dan_tautan_alat_simulasi_disembunyikan(): void
    {
        config(['antrol.tampilkan_alat_simulasi' => false, 'antrol.mode_simulasi' => true]);

        $this->get(route('antrol.pendaftaran.poli-fp'))
            ->assertOk()
            ->assertSee('Cek Poli Fingerprint')
            ->assertSee('Cek Peserta Fingerprint')
            ->assertDontSee('Pendaftaran (simulasi)')
            ->assertDontSee('ALAT SIMULASI AKTIF')
            ->assertDontSee('MODE SIMULASI - DEVELOPMENT');

        $this->get(route('antrol.alat-uji'))
            ->assertOk()
            ->assertSee('Uji service')
            ->assertSee('ANTROL_TAMPILKAN_ALAT_SIMULASI=true')
            ->assertDontSee('href="'.route('antrol.poli').'"', false)
            ->assertDontSee('href="'.route('antrol.data-uji').'"', false);

        $this->get(route('antrol.monitoring'))->assertOk()->assertDontSee('href="'.route('antrol.data-uji').'"', false);
        $this->get(route('antrol.wsbpjs'))->assertOk()->assertDontSee('href="'.route('antrol.poli').'"', false);
    }

    public function test_form_buat_antrean_farmasi_tetap_tersedia(): void
    {
        config(['antrol.tampilkan_alat_simulasi' => false, 'antrol.sumber_data' => 'bpjs']);

        $this->get(route('antrol.farmasi'))->assertOk()->assertSee('+ Buat antrean farmasi')->assertSee('name="namapasien"', false);
        $this->postJson(route('antrol.farmasi.buat'), ['kodebooking' => 'KB-CADANGAN', 'jenisresep' => 'Racikan', 'keterangan' => 'Ambil di RS']);
        $this->assertDatabaseHas('antrol_resep', ['kodebooking' => 'KB-CADANGAN', 'sumber' => 'manual']);
    }

    public function test_tautan_poli_simulasi_di_farmasi_mode_lokal_disembunyikan(): void
    {
        config(['antrol.tampilkan_alat_simulasi' => false]);

        $this->get(route('antrol.farmasi'))->assertOk()->assertDontSee('href="'.route('antrol.poli').'"', false);
    }

    public function test_mode_simulasi_diabaikan_jika_alat_disembunyikan(): void
    {
        config(['antrol.tampilkan_alat_simulasi' => false, 'antrol.mode_simulasi' => true]);
        $this->assertFalse(PelayananService::modeSimulasi());

        config(['antrol.tampilkan_alat_simulasi' => true]);
        $this->assertTrue(PelayananService::modeSimulasi());
    }

    public function test_alat_tampil_dengan_label_di_header(): void
    {
        config(['antrol.tampilkan_alat_simulasi' => true, 'antrol.mode_simulasi' => true]);
        PasienUji::factory()->create();
        JadwalUji::factory()->create();

        $this->get(route('antrol.pendaftaran'))
            ->assertOk()
            ->assertSee('ALAT SIMULASI AKTIF')
            ->assertSee('MODE SIMULASI - DEVELOPMENT')
            ->assertSee('Pendaftaran (simulasi)');
        $this->get(route('antrol.alat-uji'))->assertSee('href="'.route('antrol.poli').'"', false)->assertSee('href="'.route('antrol.data-uji').'"', false);
        $this->get(route('antrol.poli'))->assertOk();
        $this->get(route('antrol.data-uji'))->assertOk();
    }

    public function test_sinkron_jadwal_tetap_tersedia_di_monitoring_saat_alat_simulasi_mati(): void
    {
        config(['antrol.tampilkan_alat_simulasi' => false, 'antrol.sinkron_jadwal.jeda_ms' => 0]);
        Http::swap(new Factory);
        $this->palsukanBpjs([
            self::BASE.'/ref/poli' => Http::response(['response' => [['kdsubspesialis' => 'INT', 'nmsubspesialis' => 'Penyakit Dalam', 'kdpoli' => 'INT', 'nmpoli' => 'Penyakit Dalam']], 'metadata' => ['code' => 1, 'message' => 'OK']]),
            self::BASE.'/jadwaldokter/*' => Http::response(['response' => [['kodesubspesialis' => 'INT', 'namasubspesialis' => 'Penyakit Dalam', 'kodedokter' => 33690, 'namadokter' => 'dr. Andi', 'jadwal' => '08:00-12:00', 'kapasitaspasien' => 20, 'libur' => 0]], 'metadata' => ['code' => 200, 'message' => 'OK']]),
        ]);
        $fiktif = JadwalUji::factory()->create();

        $this->get(route('antrol.monitoring'))
            ->assertOk()
            ->assertSee('Sinkron jadwal dokter (HFIS)')
            ->assertSee('id="sinkron-jadwal"', false)
            ->assertSee('action="'.route('antrol.monitoring.sinkron-jadwal').'"', false)
            ->assertSee('href="'.route('antrol.monitoring').'#sinkron-jadwal"', false)
            ->assertDontSee('name="ganti_fiktif"', false);

        // Opsi ganti data fiktif diabaikan di server walaupun dikirim.
        $this->post(route('antrol.monitoring.sinkron-jadwal'), ['tanggal' => now()->toDateString(), 'mode' => 'semua', 'ganti_fiktif' => 1])
            ->assertRedirect(route('antrol.monitoring').'#sinkron-jadwal');

        $this->assertModelExists($fiktif);
        $this->assertDatabaseHas('antrol_jadwal_uji', ['sumber' => 'bpjs', 'kodepoli' => 'INT', 'kodedokter' => '33690']);
        $this->assertNull(session('sinkron')['fiktif_dihapus']);
        $this->get(route('antrol.monitoring'))->assertSee('Hasil sinkron tanggal')->assertSee('INT|33690|08:00-12:00');
    }

    public function test_opsi_ganti_data_fiktif_tampil_jika_alat_simulasi_aktif_dan_data_uji_tanpa_panel_sinkron(): void
    {
        config(['antrol.tampilkan_alat_simulasi' => true]);

        $this->get(route('antrol.monitoring'))->assertSee('name="ganti_fiktif"', false);
        $this->get(route('antrol.data-uji'))->assertOk()->assertDontSee('Sinkron jadwal dokter (HFIS)')->assertDontSee('name="ganti_fiktif"', false);
    }

    public function test_mode_simulasi_diabaikan_saat_poli_dijalankan_lewat_service(): void
    {
        // Jalur service (tanpa route): mode simulasi tetap dianggap false jika alat disembunyikan.
        config(['antrol.tampilkan_alat_simulasi' => false, 'antrol.mode_simulasi' => true]);
        $poli = AntreanPoliUji::factory()->create(['tanggal' => now()->toDateString()]);

        app(PelayananService::class)->aksiPoli($poli->kodebooking, 'checkin');

        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/antrean/updatewaktu'));
        $this->assertSame('checkin', $poli->fresh()->status);
    }
}
