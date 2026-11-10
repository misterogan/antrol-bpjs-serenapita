<?php

namespace Tests\Feature\Antrol;

use App\Models\Antrol\AntreanFarmasi;
use App\Models\Antrol\AntreanPoliUji;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/** Halaman Antrian Farmasi: urutan status, task ID 6/7, batal, pembaruan otomatis, dan input resep manual. */
class FarmasiAlurTest extends AntrolTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->palsukanBpjs();
    }

    public function test_urutan_status_divalidasi_di_server(): void
    {
        $f = AntreanFarmasi::factory()->create();

        $this->post(route('antrol.farmasi.aksi', [$f->kodebooking, 'selesai']))
            ->assertSessionHasErrors(['farmasi' => 'Antrean farmasi nomor '.$f->nomorantrean.' berstatus menunggu, aksi tidak dapat dilakukan.']);

        $this->post(route('antrol.farmasi.aksi', [$f->kodebooking, 'panggil']))->assertSessionHasNoErrors();
        $this->post(route('antrol.farmasi.aksi', [$f->kodebooking, 'batal']))->assertSessionHasErrors('farmasi');
        $this->post(route('antrol.farmasi.aksi', [$f->kodebooking, 'panggil']))->assertSessionHasNoErrors();
        $this->post(route('antrol.farmasi.aksi', [$f->kodebooking, 'selesai']))->assertSessionHasNoErrors();
        $this->post(route('antrol.farmasi.aksi', [$f->kodebooking, 'panggil']))->assertSessionHasErrors('farmasi');

        $f->refresh();
        $this->assertSame(AntreanFarmasi::SELESAI, $f->status);
        $this->assertNotNull($f->dipanggil_at);
        $this->assertNotNull($f->selesai_at);
    }

    public function test_panggil_dan_selesai_mengirim_task_6_dan_7(): void
    {
        $f = AntreanFarmasi::factory()->create();

        $this->post(route('antrol.farmasi.aksi', [$f->kodebooking, 'panggil']))->assertSessionHas('pesan', fn ($p) => str_contains($p, 'task ID 6 terkirim'));
        $this->post(route('antrol.farmasi.aksi', [$f->kodebooking, 'panggil']));
        $this->post(route('antrol.farmasi.aksi', [$f->kodebooking, 'selesai']));

        $f->refresh();
        $task = Http::recorded()->map(fn (array $p) => $p[0])->filter(fn (Request $r) => str_ends_with($r->url(), '/antrean/updatewaktu'))->values();
        // Panggil ulang tidak mengirim task 6 lagi karena sudah diterima BPJS.
        $this->assertSame([6, 7], $task->map(fn (Request $r) => $r['taskid'])->all());
        $this->assertSame([$f->kodebooking, $f->kodebooking], $task->map(fn (Request $r) => $r['kodebooking'])->all());
        $this->assertSame($f->dipanggil_at->getTimestampMs(), $task[0]['waktu']);
        $this->assertFalse(isset($task[1]['jenisresep']));
        $this->assertNotNull($f->task6_at);
        $this->assertNotNull($f->task7_at);
    }

    public function test_gagal_kirim_task_tidak_membatalkan_status_dan_bisa_kirim_ulang(): void
    {
        Http::swap(new Factory);
        $this->palsukanBpjs([self::BASE.'/antrean/updatewaktu' => Http::sequence()
            ->push(['metadata' => ['message' => 'TaskId=6 tidak valid', 'code' => 201]])
            ->push(['metadata' => ['message' => 'Ok', 'code' => 200]]),
        ]);
        $f = AntreanFarmasi::factory()->create();

        $this->post(route('antrol.farmasi.aksi', [$f->kodebooking, 'panggil']))
            ->assertSessionHas('peringatan', fn ($p) => str_contains($p, 'TaskId=6 tidak valid'));
        $f->refresh();
        $this->assertSame(AntreanFarmasi::DIPANGGIL, $f->status);
        $this->assertNull($f->task6_at);
        $this->assertSame('TaskId=6 tidak valid', $f->task6_pesan);
        $this->get(route('antrol.farmasi'))->assertSee('title="TaskId=6 tidak valid">gagal', false)->assertSee(route('antrol.farmasi.kirim-task', [$f->kodebooking, 6]));

        $this->post(route('antrol.farmasi.kirim-task', [$f->kodebooking, 6]))->assertSessionHasNoErrors();
        $f->refresh();
        $this->assertNotNull($f->task6_at);
        $this->assertNull($f->task6_pesan);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/antrean/updatewaktu') && $r['waktu'] === $f->dipanggil_at->getTimestampMs());
    }

    public function test_task_tidak_dikirim_jika_dimatikan_di_config(): void
    {
        config(['antrol.kirim_task_farmasi' => false]);
        $f = AntreanFarmasi::factory()->create();

        $this->post(route('antrol.farmasi.aksi', [$f->kodebooking, 'panggil']));
        $this->post(route('antrol.farmasi.aksi', [$f->kodebooking, 'selesai']));

        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/antrean/updatewaktu'));
        $this->assertSame(AntreanFarmasi::SELESAI, $f->fresh()->status);
    }

    public function test_kirim_ulang_task_7_sebelum_selesai_ditolak(): void
    {
        $f = AntreanFarmasi::factory()->status(AntreanFarmasi::DIPANGGIL)->create(['dipanggil_at' => now()]);

        $this->post(route('antrol.farmasi.kirim-task', [$f->kodebooking, 7]))
            ->assertSessionHasErrors(['farmasi' => 'Task ID 7 belum dapat dikirim untuk antrean farmasi berstatus dipanggil.']);
        Http::assertNothingSent();
    }

    public function test_batal_hanya_ubah_status_lokal_dan_tidak_dihitung_di_wsrs(): void
    {
        $poliA = AntreanPoliUji::factory()->selesai()->create();
        $poliB = AntreanPoliUji::factory()->selesai()->create();
        $this->post(route('antrol.farmasi.ambil'), ['kodebooking' => $poliA->kodebooking]);
        $this->post(route('antrol.farmasi.ambil'), ['kodebooking' => $poliB->kodebooking]);
        Http::swap(new Factory);
        $this->palsukanBpjs();

        $this->post(route('antrol.farmasi.aksi', [$poliA->kodebooking, 'batal']))->assertSessionHas('pesan', 'Antrean farmasi nomor 1 dibatalkan.');

        Http::assertNothingSent();
        $a = AntreanFarmasi::where('kodebooking', $poliA->kodebooking)->first();
        $this->assertSame(AntreanFarmasi::BATAL, $a->status);
        $this->assertNotNull($a->batal_at);

        $token = $this->getJson('/jkn/auth', ['x-username' => 'wsrs-uji', 'x-password' => 'sandi-uji'])->json('response.token');
        $status = $this->postJson('/jkn/status-antrean-farmasi', ['kodebooking' => $poliB->kodebooking], ['x-username' => 'wsrs-uji', 'x-token' => $token])->json('response');
        $this->assertSame(['totalantrean' => 1, 'sisaantrean' => 1, 'antreanpanggil' => 0], array_intersect_key($status, array_flip(['totalantrean', 'sisaantrean', 'antreanpanggil'])));

        $this->getJson(route('antrol.farmasi.data'))
            ->assertOk()
            ->assertJsonPath('statistik.total', 1)
            ->assertJsonPath('statistik.sisa', 1)
            ->assertJsonPath('statistik.batal', 1);
    }

    public function test_data_json_untuk_pembaruan_otomatis(): void
    {
        AntreanFarmasi::factory()->create(['nomorantrean' => 7, 'nama' => 'PASIEN ONSITE', 'norm' => '000777']);
        AntreanFarmasi::factory()->create(['nomorantrean' => 8, 'sumber' => AntreanFarmasi::SUMBER_WSRS, 'nama' => null, 'norm' => '000888']);

        $json = $this->getJson(route('antrol.farmasi.data'))->assertOk()->json();

        $this->assertSame(2, $json['statistik']['total']);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $json['diperbarui']);
        $this->assertStringContainsString('PASIEN ONSITE', $json['baris']);
        $this->assertStringContainsString('000777', $json['baris']);
        $this->assertStringContainsString('(nama belum dikirim SIMRS)', $json['baris']);
        $this->assertStringContainsString('<span class="lencana checkin">Mobile JKN</span>', $json['baris']);
        $this->assertStringContainsString('Dari Mobile JKN', $json['baris']);
        $this->assertStringContainsString('Batalkan antrean farmasi nomor 7?', $json['baris']);
        $this->assertStringContainsString('Total antrean', $json['ringkasan']);
    }

    public function test_halaman_farmasi_memakai_polling_10_detik(): void
    {
        $this->get(route('antrol.farmasi'))
            ->assertOk()
            ->assertSee('setInterval(muat, 10000)', false)
            ->assertSee(json_encode(route('antrol.farmasi.data')), false)
            ->assertSee('Muat ulang')
            ->assertSeeInOrder(['No.', 'Nama pasien', 'No. RM', 'Kode booking', 'Jenis resep', 'Sumber', 'Status', 'Waktu', 'Status BPJS', 'Aksi']);
    }

    public function test_ambil_onsite_menampilkan_nomor_dan_bukti_bpjs(): void
    {
        $poli = AntreanPoliUji::factory()->selesai('Non Racikan')->create();

        $this->post(route('antrol.farmasi.ambil'), ['kodebooking' => $poli->kodebooking]);

        $this->get(route('antrol.farmasi'))
            ->assertSeeInOrder(['Nomor antrean farmasi', '1', $poli->kodebooking, 'Non Racikan', 'terkirim', 'Lihat request &amp; response BPJS', '/antrean/farmasi/add'], false);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/antrean/farmasi/add') && $r['keterangan'] === config('antrol.keterangan_farmasi_default'));
    }
}
