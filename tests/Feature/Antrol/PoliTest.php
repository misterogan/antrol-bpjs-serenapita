<?php

namespace Tests\Feature\Antrol;

use App\Models\Antrol\AntreanPoliUji;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class PoliTest extends AntrolTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->palsukanBpjs();
    }

    public function test_halaman_poli_menampilkan_antrean_hari_ini(): void
    {
        $a = AntreanPoliUji::factory()->create(['nama' => 'PASIEN POLI']);

        $this->get(route('antrol.poli'))
            ->assertOk()
            ->assertSee('PASIEN POLI')
            ->assertSee($a->nomorantrean)
            ->assertSee('Check in');
    }

    public function test_alur_poli_berurutan_sampai_selesai_dengan_resep(): void
    {
        $a = AntreanPoliUji::factory()->create();
        $kb = $a->kodebooking;

        // Tidak boleh melompat langsung ke mulai pelayanan.
        $this->post(route('antrol.poli.aksi', [$kb, 'mulai']))->assertSessionHasErrors('poli');

        $this->post(route('antrol.poli.aksi', [$kb, 'checkin']))->assertSessionHasNoErrors();
        $this->post(route('antrol.poli.aksi', [$kb, 'panggil']))->assertSessionHasNoErrors();
        $this->post(route('antrol.poli.aksi', [$kb, 'mulai']))->assertSessionHasNoErrors();

        // Selesai tanpa memilih resep ditolak.
        $this->post(route('antrol.poli.aksi', [$kb, 'selesai']))->assertSessionHasErrors('poli');
        $this->assertNull($a->refresh()->taskid5_at);

        $this->post(route('antrol.poli.aksi', [$kb, 'selesai']), ['jenisresep' => 'Racikan'])->assertSessionHasNoErrors();

        $a->refresh();
        $this->assertSame('selesai', $a->status);
        $this->assertSame('Racikan', $a->jenisresep);
        $this->assertNotNull($a->taskid3_at);
        $this->assertNotNull($a->taskid4_at);
        $this->assertNotNull($a->taskid5_at);
        $this->assertTrue($a->checkin);

        // Mode simulasi mati: tidak ada task ID yang dikirim.
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/antrean/updatewaktu'));
    }

    public function test_selesai_tanpa_resep_tetap_menandai_task_id_5(): void
    {
        $a = AntreanPoliUji::factory()->create(['status' => 'dilayani']);

        $this->post(route('antrol.poli.aksi', [$a->kodebooking, 'selesai']), ['jenisresep' => 'Tidak ada'])->assertSessionHasNoErrors();

        $this->assertSame('Tidak ada', $a->refresh()->jenisresep);
        $this->assertNotNull($a->taskid5_at);
    }

    public function test_batalkan_antrean(): void
    {
        $a = AntreanPoliUji::factory()->create(['status' => 'checkin']);

        $this->post(route('antrol.poli.aksi', [$a->kodebooking, 'batal']))->assertSessionHasNoErrors();

        $a->refresh();
        $this->assertTrue($a->batal);
        $this->assertSame('batal', $a->status);
        $this->post(route('antrol.poli.aksi', [$a->kodebooking, 'checkin']))->assertSessionHasErrors('poli');
    }

    public function test_mode_simulasi_mengirim_update_waktu_task_3_4_5(): void
    {
        config(['antrol.mode_simulasi' => true]);
        $kb = AntreanPoliUji::factory()->create()->kodebooking;

        foreach (['checkin', 'panggil', 'mulai'] as $aksi) {
            $this->post(route('antrol.poli.aksi', [$kb, $aksi]));
        }
        $this->post(route('antrol.poli.aksi', [$kb, 'selesai']), ['jenisresep' => 'Non Racikan']);

        $terkirim = collect(Http::recorded())
            ->map(fn ($pasangan) => $pasangan[0])
            ->filter(fn (Request $r) => str_ends_with($r->url(), '/antrean/updatewaktu'))
            ->values();

        $this->assertSame([3, 4, 5], $terkirim->map(fn (Request $r) => $r['taskid'])->all());
        $this->assertArrayNotHasKey('jenisresep', $terkirim[0]->data());
        $this->assertSame('Non racikan', $terkirim[2]['jenisresep']);
        $this->assertSame($kb, $terkirim[2]['kodebooking']);
        $this->assertIsInt($terkirim[2]['waktu']);
    }

    public function test_kode_booking_tidak_ditemukan(): void
    {
        $this->post(route('antrol.poli.aksi', ['TIDAKADA', 'checkin']))
            ->assertSessionHasErrors(['poli' => 'Kode booking tidak ditemukan']);
    }
}
