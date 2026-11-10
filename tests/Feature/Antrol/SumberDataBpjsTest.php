<?php

namespace Tests\Feature\Antrol;

use App\Models\Antrol\AntreanFarmasi;
use App\Models\Antrol\Resep;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/**
 * ANTROL_SUMBER_DATA=bpjs: antrean farmasi diperiksa ke BPJS (Antrean Per Kode Booking, List Waktu Task ID)
 * dan data resep dari SIMRS vendor. Jawaban BPJS di sini contoh buatan (lihat config antrol.respons).
 */
class SumberDataBpjsTest extends AntrolTestCase
{
    private const KB = '20261107INT001';

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        config(['antrol.sumber_data' => 'bpjs']);
        $this->palsukan();
        $this->token = $this->getJson('/jkn/auth', ['x-username' => 'wsrs-uji', 'x-password' => 'sandi-uji'])->json('response.token');
    }

    public function test_wsrs_ambil_lolos_pemeriksaan_bpjs_dan_resep(): void
    {
        Resep::factory()->create(['kodebooking' => self::KB, 'jenisresep' => 'Non Racikan', 'namapasien' => 'BUDI SANTOSO', 'norekammedis' => '000101']);

        $this->ambilWsrs()
            ->assertOk()
            ->assertExactJson(['response' => ['jenisresep' => 'Non Racikan', 'nomorantrean' => 1, 'keterangan' => ''], 'metadata' => ['message' => 'Ok', 'code' => 200]]);

        $f = AntreanFarmasi::sole();
        $this->assertSame(['BUDI SANTOSO', '000101', AntreanFarmasi::SUMBER_WSRS], [$f->nama, $f->norm, $f->sumber]);
        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'/antrean/pendaftaran/kodebooking/'.self::KB);
        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'/antrean/getlisttask' && $r['kodebooking'] === self::KB);

        $this->postJson('/jkn/status-antrean-farmasi', ['kodebooking' => self::KB], $this->headerWsrs())
            ->assertExactJson(['response' => ['jenisresep' => 'Non Racikan', 'totalantrean' => 1, 'sisaantrean' => 1, 'antreanpanggil' => 0, 'keterangan' => ''], 'metadata' => ['message' => 'Ok', 'code' => 200]]);
    }

    public function test_kode_booking_tidak_ditemukan_di_bpjs(): void
    {
        $this->palsukan(antrean: Http::response(['metadata' => ['message' => 'Data tidak ditemukan', 'code' => 201]]));

        $this->ambilWsrs()->assertStatus(201)->assertExactJson(['metadata' => ['message' => 'Kode Booking tidak ditemukan', 'code' => 201]]);
    }

    public function test_antrean_batal_dan_bukan_hari_ini_ditolak(): void
    {
        Resep::factory()->create(['kodebooking' => self::KB]);

        $this->palsukan(antrean: $this->jawabanAntrean(status: 'Batal'));
        $this->ambilWsrs()->assertExactJson(['metadata' => ['message' => 'Antrean dengan kode booking tersebut sudah dibatalkan', 'code' => 201]]);

        $kemarin = now()->subDay()->toDateString();
        $this->palsukan(antrean: $this->jawabanAntrean(tanggal: $kemarin));
        $this->ambilWsrs()->assertExactJson(['metadata' => ['message' => 'Antrean farmasi hanya dapat diambil pada tanggal antrean poli ('.$kemarin.')', 'code' => 201]]);
    }

    public function test_belum_ada_task_id_5_ditolak(): void
    {
        Resep::factory()->create(['kodebooking' => self::KB]);
        $pesan = ['metadata' => ['message' => 'Pelayanan poli belum selesai, antrean farmasi belum dapat diambil', 'code' => 201]];

        $this->palsukan(task: $this->jawabanTask([1, 2, 3, 4]));
        $this->ambilWsrs()->assertExactJson($pesan);

        $this->palsukan(task: Http::response(['metadata' => ['message' => 'Data tidak ditemukan', 'code' => 201]]));
        $this->ambilWsrs()->assertExactJson($pesan);
        $this->assertSame(0, AntreanFarmasi::count());
    }

    public function test_resep_belum_ada_tidak_ada_atau_dibatalkan_ditolak(): void
    {
        $this->ambilWsrs()->assertExactJson(['metadata' => ['message' => 'Jenis resep belum tersedia, silakan hubungi farmasi', 'code' => 201]]);

        $resep = Resep::factory()->create(['kodebooking' => self::KB, 'jenisresep' => 'Tidak ada']);
        $tanpaResep = ['metadata' => ['message' => 'Pasien tidak memiliki resep, antrean farmasi tidak dapat diambil', 'code' => 201]];
        $this->ambilWsrs()->assertExactJson($tanpaResep);

        $resep->update(['jenisresep' => 'Racikan', 'dibatalkan_at' => now()]);
        $this->ambilWsrs()->assertExactJson($tanpaResep);
        $this->assertSame(0, AntreanFarmasi::count());
    }

    public function test_bpjs_tidak_bisa_dihubungi_atau_jawaban_tak_dikenal_ditolak(): void
    {
        Resep::factory()->create(['kodebooking' => self::KB]);
        $ditolak = ['metadata' => ['message' => 'Data antrean BPJS tidak dapat diperiksa, silakan coba lagi', 'code' => 201]];

        $this->palsukan(antrean: Http::failedConnection('cURL error 28: Operation timed out'));
        $this->ambilWsrs()->assertExactJson($ditolak);
        $this->postJson('/jkn/status-antrean-farmasi', ['kodebooking' => self::KB], $this->headerWsrs())->assertExactJson($ditolak);

        $this->palsukan(antrean: Http::response(['metadata' => ['message' => 'Gangguan sistem', 'code' => 201]]));
        $this->ambilWsrs()->assertExactJson($ditolak);

        $this->palsukan(task: Http::failedConnection('cURL error 6: Could not resolve host'));
        $this->ambilWsrs()->assertExactJson($ditolak);

        $this->assertSame(0, AntreanFarmasi::count());
    }

    public function test_hanya_hasil_yang_lolos_yang_di_cache_dua_menit(): void
    {
        Resep::factory()->create(['kodebooking' => self::KB]);

        // Belum task ID 5: tidak di-cache, pemeriksaan berikutnya memanggil BPJS lagi.
        $this->palsukan(task: $this->jawabanTask([1, 2, 3, 4]));
        $this->postJson('/jkn/status-antrean-farmasi', ['kodebooking' => self::KB], $this->headerWsrs());
        $this->postJson('/jkn/status-antrean-farmasi', ['kodebooking' => self::KB], $this->headerWsrs());
        $this->assertSame(2, $this->jumlahPanggilan('/kodebooking/'));

        // Lolos: di-cache, ambil antrean tidak memanggil BPJS lagi sampai 2 menit.
        $this->palsukan();
        $this->postJson('/jkn/status-antrean-farmasi', ['kodebooking' => self::KB], $this->headerWsrs());
        $this->ambilWsrs()->assertOk();
        $this->assertSame(1, $this->jumlahPanggilan('/kodebooking/'));

        $this->travel(121)->seconds();
        $this->postJson('/jkn/status-antrean-farmasi', ['kodebooking' => self::KB], $this->headerWsrs());
        $this->assertSame(2, $this->jumlahPanggilan('/kodebooking/'));
    }

    public function test_nama_belum_dikirim_memakai_no_rm_dari_bpjs(): void
    {
        Resep::factory()->tanpaNama()->create(['kodebooking' => self::KB, 'norekammedis' => null]);

        $this->ambilWsrs()->assertOk();

        $f = AntreanFarmasi::sole();
        $this->assertNull($f->nama);
        $this->assertSame('000909', $f->norm);
        $this->get(route('antrol.farmasi'))->assertSee('(nama belum dikirim SIMRS)')->assertSee('000909');
    }

    public function test_ambil_onsite_mode_bpjs_dari_daftar_resep_dan_kirim_tambah_antrean_farmasi(): void
    {
        Resep::factory()->create(['kodebooking' => self::KB, 'namapasien' => 'SIAP ONSITE']);
        Resep::factory()->create(['kodebooking' => 'KB-TIDAKADA', 'jenisresep' => 'Tidak ada', 'namapasien' => 'TANPA RESEP']);
        Resep::factory()->create(['kodebooking' => 'KB-BATAL', 'namapasien' => 'RESEP BATAL', 'dibatalkan_at' => now()]);

        $this->get(route('antrol.farmasi'))->assertSee('SIAP ONSITE')->assertDontSee('TANPA RESEP')->assertDontSee('RESEP BATAL');

        $this->post(route('antrol.farmasi.ambil'), ['kodebooking' => self::KB])->assertSessionHasNoErrors();

        $this->assertSame(AntreanFarmasi::SUMBER_ONSITE, AntreanFarmasi::sole()->sumber);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/antrean/farmasi/add') && $r['kodebooking'] === self::KB && $r['jenisresep'] === 'racikan');
        $this->get(route('antrol.farmasi'))->assertDontSee('pasien dari SIMRS siap dibuatkan antrean');
    }

    private function ambilWsrs(): TestResponse
    {
        return $this->postJson('/jkn/ambil-antrean-farmasi', ['kodebooking' => self::KB], $this->headerWsrs());
    }

    /** @return array<string, string> */
    private function headerWsrs(): array
    {
        return ['x-username' => 'wsrs-uji', 'x-token' => $this->token];
    }

    private function palsukan(mixed $antrean = null, mixed $task = null): void
    {
        Http::swap(new Factory);
        $this->palsukanBpjs([
            self::BASE.'/antrean/pendaftaran/kodebooking/*' => $antrean ?? $this->jawabanAntrean(),
            self::BASE.'/antrean/getlisttask' => $task ?? $this->jawabanTask([1, 2, 3, 4, 5]),
        ]);
    }

    private function jawabanAntrean(?string $tanggal = null, string $status = 'Belum dilayani'): mixed
    {
        return Http::response(['response' => [[
            'kodebooking' => self::KB, 'tanggal' => $tanggal ?? now()->toDateString(), 'kodepoli' => 'INT', 'kodedokter' => 33690,
            'jampraktek' => '08:00-12:00', 'nik' => '3212345678987654', 'nokapst' => '0001234567890', 'nohp' => '085600000000',
            'norekammedis' => '000909', 'jeniskunjungan' => 1, 'nomorreferensi' => 'RUJ001', 'sumberdata' => 'Bridging Antrean',
            'ispeserta' => 1, 'noantrean' => 'INT-001', 'estimasidilayani' => 1794016800000, 'createdtime' => 1794009600000, 'status' => $status,
        ]], 'metadata' => ['message' => 'Ok', 'code' => 200]]);
    }

    /** @param  list<int>  $taskid */
    private function jawabanTask(array $taskid): mixed
    {
        return Http::response(['response' => array_map(fn (int $t) => [
            'wakturs' => now()->format('d-m-Y H:i:s').' WIB', 'waktu' => now()->format('d-m-Y H:i:s').' WIB', 'taskname' => 'task '.$t, 'taskid' => $t, 'kodebooking' => self::KB,
        ], $taskid), 'metadata' => ['message' => 'Ok', 'code' => 200]]);
    }

    private function jumlahPanggilan(string $potonganUrl): int
    {
        return Http::recorded()->filter(fn (array $p) => str_contains($p[0]->url(), $potonganUrl))->count();
    }
}
