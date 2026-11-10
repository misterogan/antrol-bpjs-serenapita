<?php

namespace Tests\Feature\Antrol;

use App\Models\Antrol\AntreanFarmasi;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * "+ Buat antrean farmasi" (onsite) dengan nomor dari SIMRS vendor, cek status per baris,
 * dan WSRS lokal 404 saat ANTROL_WSRS_PROVIDER=vendor. Semua panggilan HTTP dipalsukan.
 */
class FarmasiBuatAntreanVendorTest extends AntrolTestCase
{
    private const KB = '20261110INT001';

    private const VENDOR = 'https://simrs.test';

    private const ISIAN = ['kodebooking' => self::KB, 'jenisresep' => 'Racikan', 'keterangan' => 'Ambil di RS'];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'antrol.sumber_data' => 'bpjs',
            'antrol.wsrs.provider' => 'vendor',
            'antrol.nomor_farmasi_dari' => 'vendor',
            'antrol.wsrs.vendor.base_url' => self::VENDOR,
            'antrol.wsrs.vendor.username' => 'rs-uji',
            'antrol.wsrs.vendor.password' => 'sandi-vendor',
        ]);
    }

    public function test_sukses_nomor_dari_vendor_dikirim_ke_bpjs_dan_disimpan_onsite(): void
    {
        $this->palsukan();

        $json = $this->postJson(route('antrol.farmasi.buat'), self::ISIAN)->assertOk()->json();

        $this->assertSame([true, 17, true], [$json['ok'], $json['nomorantrean'], $json['terkirim']]);
        $f = AntreanFarmasi::sole();
        $this->assertSame([17, AntreanFarmasi::SUMBER_ONSITE, 'Racikan', 'Ambil di RS'], [$f->nomorantrean, $f->sumber, $f->jenisresep, $f->keterangan]);
        $this->assertNotNull($f->dikirim_bpjs_at);
        Http::assertSent(fn (Request $r) => $r->url() === self::VENDOR.'/jkn/ambil-antrean-farmasi' && $r['kodebooking'] === self::KB);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/antrean/farmasi/add')
            && $r['nomorantrean'] === 17 && $r['jenisresep'] === 'racikan' && $r['keterangan'] === 'Ambil di RS');

        $this->assertStringContainsString('<span class="lencana">Onsite</span>', $this->getJson(route('antrol.farmasi.data'))->json('baris'));
    }

    public function test_task_5_belum_ada_ditolak_tanpa_memanggil_vendor(): void
    {
        $this->palsukan(task: [1, 2, 3, 4]);

        $this->postJson(route('antrol.farmasi.buat'), self::ISIAN)
            ->assertStatus(422)
            ->assertExactJson(['ok' => false, 'pesan' => 'Pelayanan poli belum selesai, antrean farmasi belum dapat diambil']);

        $this->assertSame(0, AntreanFarmasi::count());
        $this->assertVendorTidakDipanggil();
    }

    public function test_duplikat_kodebooking_ditolak_tanpa_memanggil_vendor(): void
    {
        $this->palsukan();
        AntreanFarmasi::factory()->create(['kodebooking' => self::KB, 'nomorantrean' => 4]);

        $this->postJson(route('antrol.farmasi.buat'), self::ISIAN)
            ->assertStatus(422)
            ->assertJsonPath('pesan', 'Kode booking sudah mengambil antrean farmasi hari ini dengan nomor 4');

        $this->assertSame(1, AntreanFarmasi::count());
        $this->assertVendorTidakDipanggil();
    }

    public function test_vendor_gagal_pesan_vendor_tampil_dan_tidak_dikirim_ke_bpjs(): void
    {
        $this->palsukan(vendor: Http::response(['metadata' => ['message' => 'Antrean farmasi sudah diambil', 'code' => 201]]));

        $this->post(route('antrol.farmasi.buat'), self::ISIAN)
            ->assertRedirect(route('antrol.farmasi'))
            ->assertSessionHasErrors(['farmasi' => 'SIMRS vendor: Antrean farmasi sudah diambil']);

        $this->assertSame(0, AntreanFarmasi::count());
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/antrean/farmasi/add'));
    }

    public function test_vendor_nomor_tidak_valid_ditolak(): void
    {
        $this->palsukan(vendor: Http::response(['response' => ['nomorantrean' => ''], 'metadata' => ['message' => 'Ok', 'code' => 200]]));

        $this->postJson(route('antrol.farmasi.buat'), self::ISIAN)
            ->assertStatus(422)
            ->assertJsonPath('pesan', 'SIMRS vendor: nomor antrean farmasi tidak valid ("").');
        $this->assertSame(0, AntreanFarmasi::count());
    }

    public function test_bpjs_gagal_antrean_tersimpan_dengan_peringatan_pesan_bpjs(): void
    {
        $this->palsukan(bpjsFarmasi: Http::response(['metadata' => ['message' => 'data keterangan belum sesuai', 'code' => 201]]));

        $json = $this->postJson(route('antrol.farmasi.buat'), self::ISIAN)->assertOk()->json();

        $this->assertFalse($json['terkirim']);
        $this->assertStringContainsString('data keterangan belum sesuai', $json['peringatan']);
        $this->assertNull(AntreanFarmasi::sole()->dikirim_bpjs_at);
    }

    public function test_mode_nomor_lokal_tidak_memanggil_vendor(): void
    {
        config(['antrol.nomor_farmasi_dari' => 'lokal']);
        $this->palsukan();
        AntreanFarmasi::factory()->create(['nomorantrean' => 3]);

        $this->postJson(route('antrol.farmasi.buat'), self::ISIAN)->assertOk()->assertJsonPath('nomorantrean', 4);

        $this->assertVendorTidakDipanggil();
    }

    public function test_keterangan_wajib_diisi(): void
    {
        $this->palsukan();

        $this->postJson(route('antrol.farmasi.buat'), ['keterangan' => ''] + self::ISIAN)
            ->assertStatus(422)
            ->assertExactJson(['ok' => false, 'pesan' => 'Keterangan wajib diisi.']);
        Http::assertNothingSent();
    }

    public function test_cek_status_per_baris_dari_vendor(): void
    {
        $this->palsukan(vendorStatus: Http::response([
            'response' => ['jenisresep' => 'Racikan', 'totalantrean' => 5, 'sisaantrean' => 3, 'antreanpanggil' => 2, 'keterangan' => ''],
            'metadata' => ['message' => 'Ok', 'code' => 200],
        ]));
        AntreanFarmasi::factory()->create(['kodebooking' => self::KB]);

        $this->get(route('antrol.farmasi'))->assertSee('action="'.route('antrol.farmasi.cek-status', self::KB).'"', false)->assertSee('Cek status');

        $this->post(route('antrol.farmasi.cek-status', self::KB))
            ->assertRedirect(route('antrol.farmasi'))
            ->assertSessionHas('pesan', 'Status antrean farmasi '.self::KB.' (SIMRS vendor): sisa 3, dipanggil 2, total 5, Racikan.');

        // Panggil/Selesai/Batalkan tetap dari modul.
        $this->get(route('antrol.farmasi'))->assertSee(route('antrol.farmasi.aksi', [self::KB, 'panggil']), false);
    }

    public function test_cek_status_gagal_menampilkan_pesan_vendor(): void
    {
        $this->palsukan(vendorStatus: Http::response(['metadata' => ['message' => 'Antrean Tidak Ditemukan', 'code' => 201]]));

        $this->post(route('antrol.farmasi.cek-status', self::KB))
            ->assertSessionHasErrors(['farmasi' => 'Cek status '.self::KB.' ditolak SIMRS vendor: Antrean Tidak Ditemukan']);
    }

    public function test_tombol_cek_status_tidak_tampil_saat_provider_lokal(): void
    {
        config(['antrol.wsrs.provider' => 'lokal']);
        $this->palsukan();
        AntreanFarmasi::factory()->create(['kodebooking' => self::KB]);

        $this->get(route('antrol.farmasi'))->assertDontSee('Cek status');
    }

    public function test_wsrs_lokal_404_saat_provider_vendor(): void
    {
        $this->palsukan();

        foreach (['/jkn/ambil-antrean-farmasi', '/jkn/status-antrean-farmasi'] as $url) {
            $this->postJson($url, ['kodebooking' => self::KB], ['x-username' => 'wsrs-uji', 'x-token' => 'apa-saja'])
                ->assertNotFound()
                ->assertExactJson(['metadata' => ['message' => 'Service ini dilayani SIMRS vendor (ANTROL_WSRS_PROVIDER=vendor), bukan modul antrol', 'code' => 404]]);
        }
        $this->assertSame(0, AntreanFarmasi::count());

        // Cadangan: provider lokal kembali dilayani modul (melewati pemeriksaan token).
        config(['antrol.wsrs.provider' => 'lokal']);
        $this->postJson('/jkn/ambil-antrean-farmasi', ['kodebooking' => self::KB])
            ->assertExactJson(['metadata' => ['message' => 'Header x-token dan x-username wajib diisi', 'code' => 201]]);
    }

    /** @param  list<int>  $task */
    private function palsukan(array $task = [1, 2, 3, 4, 5], mixed $vendor = null, mixed $vendorStatus = null, mixed $bpjsFarmasi = null): void
    {
        Http::swap(new Factory);
        $this->palsukanBpjs([
            self::BASE.'/antrean/pendaftaran/kodebooking/*' => Http::response(['response' => [[
                'kodebooking' => self::KB, 'tanggal' => now()->toDateString(), 'norekammedis' => '000909', 'status' => 'Belum dilayani',
            ]], 'metadata' => ['message' => 'Ok', 'code' => 200]]),
            self::BASE.'/antrean/getlisttask' => Http::response(['response' => array_map(fn (int $t) => ['taskid' => $t, 'kodebooking' => self::KB], $task), 'metadata' => ['message' => 'Ok', 'code' => 200]]),
            self::BASE.'/antrean/farmasi/add' => $bpjsFarmasi ?? Http::response(['metadata' => ['message' => 'Ok', 'code' => 200]]),
            self::VENDOR.'/jkn/auth' => Http::response(['response' => ['token' => 'token-vendor'], 'metadata' => ['message' => 'Ok', 'code' => 200]]),
            self::VENDOR.'/jkn/ambil-antrean-farmasi' => $vendor ?? Http::response([
                'response' => ['jenisresep' => 'Racikan', 'nomorantrean' => 17, 'keterangan' => ''],
                'metadata' => ['message' => 'Ok', 'code' => 200],
            ]),
            self::VENDOR.'/jkn/status-antrean-farmasi' => $vendorStatus ?? Http::response(['metadata' => ['message' => 'Ok', 'code' => 200]]),
        ]);
    }

    private function assertVendorTidakDipanggil(): void
    {
        Http::assertNotSent(fn (Request $r) => str_starts_with($r->url(), self::VENDOR));
    }
}
