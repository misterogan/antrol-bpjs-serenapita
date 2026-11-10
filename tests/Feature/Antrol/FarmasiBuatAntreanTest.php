<?php

namespace Tests\Feature\Antrol;

use App\Models\Antrol\AntreanFarmasi;
use App\Models\Antrol\AntreanPoliUji;
use App\Models\Antrol\Resep;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/** Form tunggal "+ Buat antrean farmasi" di halaman Antrian Farmasi. */
class FarmasiBuatAntreanTest extends AntrolTestCase
{
    private const KB = '20261107INT001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->palsukanBpjs();
    }

    public function test_halaman_memakai_satu_tombol_dan_form(): void
    {
        $this->get(route('antrol.farmasi'))
            ->assertOk()
            ->assertSee('+ Buat antrean farmasi')
            ->assertSee('<dialog id="dialog-buat"', false)
            ->assertSee('action="'.route('antrol.farmasi.buat').'"', false)
            ->assertSee('name="kodebooking" required', false)
            ->assertSee('name="namapasien"', false)
            ->assertSee('name="norekammedis"', false)
            ->assertSee('>Racikan</option>', false)
            ->assertSee('>Non Racikan</option>', false)
            ->assertSee('value="'.config('antrol.keterangan_farmasi_default').'"', false)
            ->assertDontSee('Siap ambil antrean farmasi')
            ->assertDontSee('Ambil dengan kode booking')
            ->assertDontSee('Input resep manual');
    }

    public function test_cari_resep_untuk_isi_otomatis(): void
    {
        config(['antrol.sumber_data' => 'bpjs']);
        Resep::factory()->create(['kodebooking' => 'KB-VENDOR', 'namapasien' => 'BUDI', 'norekammedis' => '000101', 'jenisresep' => 'Non Racikan']);
        Resep::factory()->create(['kodebooking' => 'KB-MANUAL', 'sumber' => 'manual', 'jenisresep' => 'Racikan']);
        Resep::factory()->create(['kodebooking' => 'KB-TIDAKADA', 'jenisresep' => 'Tidak ada']);
        Resep::factory()->create(['kodebooking' => 'KB-BATAL', 'dibatalkan_at' => now()]);

        $this->getJson(route('antrol.farmasi.resep', 'KB-VENDOR'))
            ->assertExactJson(['ada' => true, 'namapasien' => 'BUDI', 'norekammedis' => '000101', 'jenisresep' => 'Non Racikan', 'sumber' => 'vendor', 'label' => 'data dari SIMRS']);
        $this->getJson(route('antrol.farmasi.resep', 'KB-MANUAL'))->assertJsonPath('label', 'data resep sebelumnya');
        $this->getJson(route('antrol.farmasi.resep', 'KB-TIDAKADA'))->assertJsonPath('jenisresep', null);
        $this->getJson(route('antrol.farmasi.resep', 'KB-BATAL'))->assertExactJson(['ada' => false]);
        $this->getJson(route('antrol.farmasi.resep', 'TIDAKADA'))->assertExactJson(['ada' => false]);

        // Mode bpjs: isi otomatis tidak memanggil BPJS.
        Http::assertNothingSent();
    }

    public function test_cari_resep_mode_lokal_memakai_data_uji_poli(): void
    {
        $poli = AntreanPoliUji::factory()->selesai('Racikan')->create(['nama' => 'PASIEN UJI', 'norm' => '000777']);

        $this->getJson(route('antrol.farmasi.resep', $poli->kodebooking))
            ->assertJsonPath('ada', true)
            ->assertJsonPath('namapasien', 'PASIEN UJI')
            ->assertJsonPath('norekammedis', '000777')
            ->assertJsonPath('jenisresep', 'Racikan')
            ->assertJsonPath('label', 'data dari data uji poli');
    }

    public function test_simpan_membuat_antrean_dan_mengirim_tambah_antrean_farmasi(): void
    {
        $this->modeBpjs();

        $json = $this->postJson(route('antrol.farmasi.buat'), [
            'kodebooking' => self::KB, 'namapasien' => 'siti aminah', 'norekammedis' => '000103', 'jenisresep' => 'Non Racikan', 'keterangan' => 'Ambil di RS',
        ])->assertOk()->json();

        $this->assertTrue($json['ok']);
        $this->assertSame('Antrean farmasi nomor 1 dibuat.', $json['pesan']);
        $this->assertSame(1, $json['nomorantrean']);
        $this->assertTrue($json['terkirim']);
        $this->assertStringContainsString('Nomor antrean farmasi', $json['tiket']);
        $this->assertStringContainsString('Lihat request &amp; response BPJS', $json['tiket']);

        $this->assertDatabaseHas('antrol_resep', ['kodebooking' => self::KB, 'namapasien' => 'SITI AMINAH', 'norekammedis' => '000103', 'jenisresep' => 'Non Racikan', 'sumber' => 'manual']);
        $f = AntreanFarmasi::sole();
        $this->assertSame(['SITI AMINAH', '000103', 'onsite'], [$f->nama, $f->norm, $f->sumber]);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/antrean/farmasi/add')
            && $r['kodebooking'] === self::KB && $r['jenisresep'] === 'non racikan' && $r['keterangan'] === 'Ambil di RS');

        // Baris baru bisa disorot: tabel memuat data-kodebooking.
        $this->assertStringContainsString('data-kodebooking="'.self::KB.'"', $this->getJson(route('antrol.farmasi.data'))->json('baris'));
    }

    public function test_ditolak_form_tetap_dengan_alasan_dan_resep_tersimpan(): void
    {
        $this->modeBpjs(task: [1, 2, 3, 4]);

        $this->postJson(route('antrol.farmasi.buat'), ['kodebooking' => self::KB, 'namapasien' => 'Pasien Coba', 'jenisresep' => 'Racikan', 'keterangan' => 'Ambil di RS'])
            ->assertStatus(422)
            ->assertExactJson(['ok' => false, 'pesan' => 'Pelayanan poli belum selesai, antrean farmasi belum dapat diambil']);

        $this->assertSame(0, AntreanFarmasi::count());
        $this->assertDatabaseHas('antrol_resep', ['kodebooking' => self::KB, 'namapasien' => 'PASIEN COBA', 'sumber' => 'manual']);
    }

    public function test_ditolak_tanggal_bukan_hari_ini_atau_batal(): void
    {
        $this->modeBpjs(tanggal: now()->subDay()->toDateString());
        $this->postJson(route('antrol.farmasi.buat'), ['kodebooking' => self::KB, 'jenisresep' => 'Racikan', 'keterangan' => 'Ambil di RS'])
            ->assertStatus(422)->assertJsonPath('pesan', 'Antrean farmasi hanya dapat diambil pada tanggal antrean poli ('.now()->subDay()->toDateString().')');

        $this->modeBpjs(status: 'Batal');
        $this->postJson(route('antrol.farmasi.buat'), ['kodebooking' => self::KB, 'jenisresep' => 'Racikan', 'keterangan' => 'Ambil di RS'])
            ->assertStatus(422)->assertJsonPath('pesan', 'Antrean dengan kode booking tersebut sudah dibatalkan');
    }

    public function test_data_vendor_tidak_ditimpa_kecuali_diubah_petugas(): void
    {
        $this->modeBpjs(task: [1, 2, 3, 4]);
        $vendor = Resep::factory()->create(['kodebooking' => self::KB, 'namapasien' => 'BUDI SANTOSO', 'norekammedis' => '000101', 'jenisresep' => 'Racikan']);

        // Isian sama (nama & RM dikosongkan) → data vendor tetap.
        $this->postJson(route('antrol.farmasi.buat'), ['kodebooking' => self::KB, 'namapasien' => '', 'norekammedis' => '', 'jenisresep' => 'Racikan', 'keterangan' => 'Ambil di RS']);
        $vendor->refresh();
        $this->assertSame(['vendor', 'BUDI SANTOSO', '000101'], [$vendor->sumber, $vendor->namapasien, $vendor->norekammedis]);

        // Petugas mengubah jenis resep → diperbarui sebagai input manual, data lain dipertahankan.
        $this->postJson(route('antrol.farmasi.buat'), ['kodebooking' => self::KB, 'jenisresep' => 'Non Racikan', 'keterangan' => 'Ambil di RS']);
        $vendor->refresh();
        $this->assertSame(['manual', 'Non Racikan', 'BUDI SANTOSO', '000101'], [$vendor->sumber, $vendor->jenisresep, $vendor->namapasien, $vendor->norekammedis]);
    }

    public function test_validasi_form(): void
    {
        $this->postJson(route('antrol.farmasi.buat'), ['jenisresep' => 'Racikan', 'keterangan' => 'Ambil di RS'])
            ->assertStatus(422)->assertExactJson(['ok' => false, 'pesan' => 'Kode booking poli wajib diisi.']);
        $this->postJson(route('antrol.farmasi.buat'), ['kodebooking' => 'KB1', 'jenisresep' => 'Tidak ada'])
            ->assertStatus(422)->assertJsonPath('ok', false);
        $this->postJson(route('antrol.farmasi.buat'), ['kodebooking' => '../x', 'jenisresep' => 'Racikan', 'keterangan' => 'Ambil di RS'])->assertStatus(422);

        $this->assertSame(0, Resep::count());
        Http::assertNothingSent();
    }

    public function test_tanpa_javascript_ditolak_kembali_ke_halaman_dengan_form_terbuka(): void
    {
        $this->post(route('antrol.farmasi.buat'), ['kodebooking' => 'TIDAKADA', 'jenisresep' => 'Racikan', 'keterangan' => 'Ambil di RS'])
            ->assertRedirect(route('antrol.farmasi'))
            ->assertSessionHasErrors(['farmasi' => 'Kode Booking tidak ditemukan'])
            ->assertSessionHas('buka_form', true);
    }

    public function test_baris_pasien_dari_simrs_siap_dibuatkan_antrean(): void
    {
        config(['antrol.sumber_data' => 'bpjs']);
        $this->get(route('antrol.farmasi'))->assertDontSee('siap dibuatkan antrean');

        Resep::factory()->create(['kodebooking' => 'KB-SIMRS', 'namapasien' => 'DARI SIMRS', 'norekammedis' => '000201', 'jenisresep' => 'Non Racikan']);
        Resep::factory()->create(['kodebooking' => 'KB-MANUAL', 'namapasien' => 'INPUT MANUAL', 'sumber' => 'manual']);

        $this->get(route('antrol.farmasi'))
            ->assertSee('<b>1</b> pasien dari SIMRS siap dibuatkan antrean.', false)
            ->assertSee('Lihat daftar')
            ->assertSee('data-kodebooking="KB-SIMRS" data-nama="DARI SIMRS" data-norm="000201" data-jenis="Non Racikan"', false)
            ->assertSee('data-label="data dari SIMRS">Buat antrean</button>', false)
            ->assertDontSee('INPUT MANUAL');

        $this->assertStringContainsString('DARI SIMRS', $this->getJson(route('antrol.farmasi.data'))->json('siap'));
    }

    public function test_pesan_task_gagal_ditampilkan_di_status_bpjs(): void
    {
        Http::swap(new Factory);
        $this->palsukanBpjs([self::BASE.'/antrean/updatewaktu' => Http::response(['metadata' => ['message' => 'TaskId=6 tidak valid untuk kode booking ini', 'code' => 201]])]);
        $f = AntreanFarmasi::factory()->create();

        $this->post(route('antrol.farmasi.aksi', [$f->kodebooking, 'panggil']));

        $this->get(route('antrol.farmasi'))->assertSee('gagal: TaskId=6 tidak valid untuk kode booking ini');
    }

    /** @param  list<int>  $task */
    private function modeBpjs(array $task = [1, 2, 3, 4, 5], ?string $tanggal = null, string $status = 'Belum dilayani'): void
    {
        config(['antrol.sumber_data' => 'bpjs']);
        Http::swap(new Factory);
        $this->palsukanBpjs([
            self::BASE.'/antrean/pendaftaran/kodebooking/*' => Http::response(['response' => [[
                'kodebooking' => self::KB, 'tanggal' => $tanggal ?? now()->toDateString(), 'norekammedis' => '000909', 'status' => $status,
            ]], 'metadata' => ['message' => 'Ok', 'code' => 200]]),
            self::BASE.'/antrean/getlisttask' => Http::response(['response' => array_map(fn (int $t) => ['taskid' => $t, 'kodebooking' => self::KB], $task), 'metadata' => ['message' => 'Ok', 'code' => 200]]),
        ]);
    }
}
