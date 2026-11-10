<?php

namespace Tests\Feature\Antrol;

use App\Models\Antrol\AntreanFarmasi;
use App\Models\Antrol\AntreanPoliUji;
use App\Models\Antrol\AntrolLog;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class FarmasiTest extends AntrolTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->palsukanBpjs();
    }

    public function test_pasien_selesai_poli_dengan_resep_muncul_di_daftar_siap_ambil(): void
    {
        AntreanPoliUji::factory()->selesai('Racikan')->create(['nama' => 'SIAP FARMASI']);
        AntreanPoliUji::factory()->selesai('Tidak ada')->create(['nama' => 'TANPA RESEP']);
        AntreanPoliUji::factory()->create(['nama' => 'BELUM SELESAI']);

        $this->get(route('antrol.farmasi'))
            ->assertOk()
            ->assertSee('SIAP FARMASI')
            ->assertDontSee('TANPA RESEP')
            ->assertDontSee('BELUM SELESAI');
    }

    public function test_ambil_antrean_farmasi_nomor_bertambah_dan_dikirim_ke_bpjs(): void
    {
        $a = AntreanPoliUji::factory()->selesai('Racikan')->create();
        $b = AntreanPoliUji::factory()->selesai('Non Racikan')->create();

        $this->post(route('antrol.farmasi.ambil'), ['kodebooking' => $a->kodebooking])->assertSessionHasNoErrors();
        $this->post(route('antrol.farmasi.ambil'), ['kodebooking' => $b->kodebooking])->assertSessionHasNoErrors();

        $this->assertSame(1, AntreanFarmasi::where('kodebooking', $a->kodebooking)->value('nomorantrean'));
        $this->assertSame(2, AntreanFarmasi::where('kodebooking', $b->kodebooking)->value('nomorantrean'));
        $this->assertSame(2, AntreanFarmasi::whereNotNull('dikirim_bpjs_at')->count());

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/antrean/farmasi/add')
            && $r['kodebooking'] === $b->kodebooking
            && $r['jenisresep'] === 'non racikan'
            && $r['nomorantrean'] === 2);
    }

    public function test_satu_kode_booking_hanya_satu_antrean_farmasi_per_hari(): void
    {
        $a = AntreanPoliUji::factory()->selesai()->create();

        $this->post(route('antrol.farmasi.ambil'), ['kodebooking' => $a->kodebooking])->assertSessionHasNoErrors();
        $this->post(route('antrol.farmasi.ambil'), ['kodebooking' => $a->kodebooking])
            ->assertSessionHasErrors(['farmasi' => 'Kode booking sudah mengambil antrean farmasi hari ini dengan nomor 1']);

        $this->assertSame(1, AntreanFarmasi::count());
        Http::assertSentCount(1);
    }

    public function test_kode_booking_tidak_ditemukan(): void
    {
        $this->post(route('antrol.farmasi.ambil'), ['kodebooking' => 'TIDAKADA'])
            ->assertSessionHasErrors(['farmasi' => 'Kode Booking tidak ditemukan']);

        Http::assertNothingSent();
    }

    public function test_kode_booking_tidak_ditemukan_lewat_wsrs(): void
    {
        $token = $this->tokenWsrs();

        $this->postJson('/jkn/ambil-antrean-farmasi', ['kodebooking' => 'TIDAKADA'], $this->headerWsrs($token))
            ->assertStatus(201)
            ->assertExactJson(['metadata' => ['message' => 'Kode Booking tidak ditemukan', 'code' => 201]]);

        $this->postJson('/jkn/status-antrean-farmasi', ['kodebooking' => 'TIDAKADA'], $this->headerWsrs($token))
            ->assertExactJson(['metadata' => ['message' => 'kodebooking Tidak Ditemukan', 'code' => 201]]);
    }

    public function test_belum_selesai_poli_tidak_bisa_ambil_antrean_farmasi(): void
    {
        $a = AntreanPoliUji::factory()->create(['status' => 'dilayani']);

        $this->post(route('antrol.farmasi.ambil'), ['kodebooking' => $a->kodebooking])
            ->assertSessionHasErrors(['farmasi' => 'Pelayanan poli belum selesai, antrean farmasi belum dapat diambil']);
    }

    public function test_panggil_dan_selesai_mengubah_status_antrean_farmasi(): void
    {
        $a = AntreanPoliUji::factory()->selesai('Racikan')->create();
        $b = AntreanPoliUji::factory()->selesai('Non Racikan')->create();
        $this->post(route('antrol.farmasi.ambil'), ['kodebooking' => $a->kodebooking]);
        $this->post(route('antrol.farmasi.ambil'), ['kodebooking' => $b->kodebooking]);

        $token = $this->tokenWsrs();
        $status = fn () => $this->postJson('/jkn/status-antrean-farmasi', ['kodebooking' => $b->kodebooking], $this->headerWsrs($token))->json('response');

        $this->assertSame(['totalantrean' => 2, 'sisaantrean' => 2, 'antreanpanggil' => 0], array_intersect_key($status(), array_flip(['totalantrean', 'sisaantrean', 'antreanpanggil'])));

        // Selesai sebelum dipanggil ditolak.
        $this->post(route('antrol.farmasi.aksi', [$a->kodebooking, 'selesai']))->assertSessionHasErrors('farmasi');

        $this->post(route('antrol.farmasi.aksi', [$a->kodebooking, 'panggil']))->assertSessionHasNoErrors();
        $s = $status();
        $this->assertSame(1, $s['antreanpanggil']);
        $this->assertSame(1, $s['sisaantrean']);

        $this->post(route('antrol.farmasi.aksi', [$a->kodebooking, 'selesai']))->assertSessionHasNoErrors();
        $this->post(route('antrol.farmasi.aksi', [$b->kodebooking, 'panggil']))->assertSessionHasNoErrors();
        $s = $status();
        $this->assertSame(2, $s['antreanpanggil']);
        $this->assertSame(0, $s['sisaantrean']);

        // Ringkasan di halaman Farmasi sama dengan status WSRS.
        $this->get(route('antrol.farmasi'))->assertOk()->assertSeeInOrder(['Sedang dipanggil', '2', 'Total antrean', '2', 'Sisa (menunggu)', '0', 'Selesai', '1']);
    }

    public function test_bpjs_gagal_nomor_tetap_diambil_dan_bisa_dikirim_ulang(): void
    {
        Http::swap(new Factory);
        $this->palsukanBpjs([self::BASE.'/antrean/farmasi/add' => Http::sequence()
            ->push(['metadata' => ['message' => 'Gagal uji', 'code' => 201]])
            ->push(['metadata' => ['message' => 'Ok', 'code' => 200]]),
        ]);
        $a = AntreanPoliUji::factory()->selesai()->create();

        $this->post(route('antrol.farmasi.ambil'), ['kodebooking' => $a->kodebooking])
            ->assertSessionHas('peringatan', fn (string $p) => str_contains($p, 'Gagal uji'));
        $this->assertNull(AntreanFarmasi::first()->dikirim_bpjs_at);

        $this->post(route('antrol.farmasi.kirim-ulang', $a->kodebooking))->assertSessionHasNoErrors();
        $this->assertNotNull(AntreanFarmasi::first()->dikirim_bpjs_at);
        $this->assertSame(1, AntreanFarmasi::count());
    }

    public function test_keterangan_kosong_diganti_keterangan_default_di_halaman_farmasi(): void
    {
        $a = AntreanPoliUji::factory()->selesai()->create();
        $b = AntreanPoliUji::factory()->selesai()->create();

        $this->post(route('antrol.farmasi.ambil'), ['kodebooking' => $a->kodebooking, 'keterangan' => '  ']);
        $this->post(route('antrol.farmasi.ambil'), ['kodebooking' => $b->kodebooking, 'keterangan' => 'Ambil di loket 2']);

        Http::assertSent(fn (Request $r) => $r['kodebooking'] === $a->kodebooking && $r['keterangan'] === 'Antrean farmasi diambil di rumah sakit');
        Http::assertSent(fn (Request $r) => $r['kodebooking'] === $b->kodebooking && $r['keterangan'] === 'Ambil di loket 2');
    }

    public function test_keterangan_default_mengikuti_config_dan_dipakai_saat_kirim_ulang(): void
    {
        config(['antrol.keterangan_farmasi_default' => 'Keterangan uji']);
        Http::swap(new Factory);
        $this->palsukanBpjs([self::BASE.'/antrean/farmasi/add' => Http::sequence()
            ->push(['metadata' => ['message' => 'data keterangan belum sesuai', 'code' => 201]])
            ->push(['metadata' => ['message' => 'Ok', 'code' => 200]]),
        ]);
        $a = AntreanPoliUji::factory()->selesai()->create();

        $this->post(route('antrol.farmasi.ambil'), ['kodebooking' => $a->kodebooking]);
        $this->post(route('antrol.farmasi.kirim-ulang', $a->kodebooking))->assertSessionHasNoErrors();

        $terkirim = Http::recorded()->map(fn (array $pasangan) => $pasangan[0]['keterangan'])->all();
        $this->assertSame(['Keterangan uji', 'Keterangan uji'], $terkirim);
        $this->assertSame('', AntreanFarmasi::first()->keterangan);
    }

    public function test_keterangan_kosong_diganti_keterangan_default_di_halaman_uji_service(): void
    {
        $a = AntreanPoliUji::factory()->selesai()->create();

        $this->post(route('antrol.tambah-farmasi'), ['kodebooking' => $a->kodebooking, 'keterangan' => '']);
        $this->post(route('antrol.tambah-farmasi'), ['kodebooking' => $a->kodebooking, 'jenisresep' => 'racikan', 'nomorantrean' => 7]);

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => $r['nomorantrean'] === 1 && $r['keterangan'] === 'Antrean farmasi diambil di rumah sakit');
        Http::assertSent(fn (Request $r) => $r['nomorantrean'] === 7 && $r['keterangan'] === 'Antrean farmasi diambil di rumah sakit');
    }

    public function test_antrean_dari_wsrs_ditandai_mobile_jkn_tanpa_kirim_ulang_dan_tidak_dihitung_terkirim(): void
    {
        $mobile = AntreanPoliUji::factory()->selesai('Racikan')->create();
        $onsite = AntreanPoliUji::factory()->selesai('Non Racikan')->create();

        // Respons WSRS tetap sama, termasuk keterangan "".
        $this->postJson('/jkn/ambil-antrean-farmasi', ['kodebooking' => $mobile->kodebooking], $this->headerWsrs($this->tokenWsrs()))
            ->assertOk()
            ->assertExactJson([
                'response' => ['jenisresep' => 'Racikan', 'nomorantrean' => 1, 'keterangan' => ''],
                'metadata' => ['message' => 'Ok', 'code' => 200],
            ]);
        Http::assertNothingSent();

        $this->post(route('antrol.farmasi.ambil'), ['kodebooking' => $onsite->kodebooking]);

        $this->assertSame(AntreanFarmasi::SUMBER_WSRS, AntreanFarmasi::where('kodebooking', $mobile->kodebooking)->value('sumber'));
        $this->assertSame(AntreanFarmasi::SUMBER_ONSITE, AntreanFarmasi::where('kodebooking', $onsite->kodebooking)->value('sumber'));

        $this->get(route('antrol.farmasi'))
            ->assertOk()
            ->assertSee('Dari Mobile JKN')
            ->assertSeeInOrder(['Terkirim ke BPJS', '1', '/ 1', '1 dari Mobile JKN tidak dihitung'])
            ->assertDontSee(route('antrol.farmasi.kirim-ulang', $mobile->kodebooking));
        Http::assertSentCount(1);
    }

    public function test_kirim_ulang_ditolak_untuk_antrean_dari_mobile_jkn(): void
    {
        $a = AntreanPoliUji::factory()->selesai()->create();
        $this->postJson('/jkn/ambil-antrean-farmasi', ['kodebooking' => $a->kodebooking], $this->headerWsrs($this->tokenWsrs()))->assertOk();

        $this->post(route('antrol.farmasi.kirim-ulang', $a->kodebooking))
            ->assertSessionHasErrors(['farmasi' => 'Antrean farmasi nomor 1 diambil lewat Mobile JKN (WSRS) dan sudah diketahui BPJS, tidak perlu dikirim.']);

        Http::assertNothingSent();
    }

    public function test_migrasi_menandai_antrean_lama_dari_log_wsrs(): void
    {
        $migrasi = require database_path('migrations/2026_11_06_155351_add_sumber_to_antrol_farmasi_table.php');
        $migrasi->down();

        $tanggal = now()->toDateString();
        foreach ([['20261106MAT001', 1], ['20261106ANA001', 2]] as [$kode, $nomor]) {
            DB::table('antrol_farmasi')->insert(['kodebooking' => $kode, 'tanggal' => $tanggal, 'nomorantrean' => $nomor, 'jenisresep' => 'Racikan', 'status' => 'menunggu', 'keterangan' => '']);
        }
        $catat = fn (string $kode, array $respons) => AntrolLog::create([
            'arah' => 'wsrs', 'endpoint' => 'POST ambilantreanfarmasi', 'request' => ['kodebooking' => $kode], 'response' => $respons, 'http_status' => $respons['metadata']['code'],
        ]);
        $catat('20261106MAT001', ['response' => ['jenisresep' => 'Racikan', 'nomorantrean' => 1, 'keterangan' => ''], 'metadata' => ['message' => 'Ok', 'code' => 200]]);
        $catat('20261106ANA001', ['metadata' => ['message' => 'Pelayanan poli belum selesai, antrean farmasi belum dapat diambil', 'code' => 201]]);

        $migrasi->up();

        $this->assertSame(['20261106ANA001' => 'onsite', '20261106MAT001' => 'wsrs'], DB::table('antrol_farmasi')->orderBy('kodebooking')->pluck('sumber', 'kodebooking')->all());
    }

    private function tokenWsrs(): string
    {
        return $this->getJson('/jkn/auth', ['x-username' => 'wsrs-uji', 'x-password' => 'sandi-uji'])
            ->assertOk()
            ->json('response.token');
    }

    /** @return array<string, string> */
    private function headerWsrs(string $token): array
    {
        return ['x-username' => 'wsrs-uji', 'x-token' => $token];
    }
}
