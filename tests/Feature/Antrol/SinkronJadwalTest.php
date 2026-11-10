<?php

namespace Tests\Feature\Antrol;

use App\Models\Antrol\AntrolLog;
use App\Models\Antrol\JadwalUji;
use App\Models\Antrol\PasienUji;
use App\Models\Antrol\PoliBerjadwal;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Testing\TestResponse;

/**
 * Respons palsu sinkron (5 poli dari Referensi Poli):
 * INT 2 jadwal (1 libur), ANA 1 jadwal, MAT 201 "No Content" (tidak ada jadwal),
 * OBG 201 "Gangguan" (ditolak BPJS), THT gagal koneksi.
 */
class SinkronJadwalTest extends AntrolTestCase
{
    private string $tanggal;

    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
        $this->tanggal = now()->toDateString();
        $this->palsukanBpjs($this->responsSinkron());

        JadwalUji::factory()->poli('INT', 'PENYAKIT DALAM')->create(['kodedokter' => '10001', 'namadokter' => 'dr. Fiktif']);
    }

    public function test_sinkron_menyimpan_jadwal_bpjs_dan_mempertahankan_data_fiktif(): void
    {
        $this->sinkron()
            ->assertRedirect(route('antrol.monitoring').'#sinkron-jadwal')
            ->assertSessionHas('peringatan');

        $hari = now()->dayOfWeekIso;
        $this->assertDatabaseHas('antrol_jadwal_uji', [
            'sumber' => 'bpjs', 'hari' => $hari, 'kodepoli' => 'INT', 'kodedokter' => '33690',
            'namadokter' => 'dr. Andi, Sp.PD', 'jampraktek' => '08:00-12:00', 'kuota_jkn' => 25, 'libur' => false,
        ]);
        $this->assertDatabaseHas('antrol_jadwal_uji', ['sumber' => 'bpjs', 'kodepoli' => 'INT', 'kodedokter' => '33691', 'libur' => true]);
        $this->assertDatabaseHas('antrol_jadwal_uji', ['sumber' => 'bpjs', 'kodepoli' => 'ANA', 'kodedokter' => '44100', 'jampraktek' => '13:00-16:00']);
        $this->assertSame(3, JadwalUji::where('sumber', 'bpjs')->count());
        $this->assertDatabaseHas('antrol_jadwal_uji', ['sumber' => 'fiktif', 'kodedokter' => '10001']);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/jadwaldokter/kodepoli/INT/tanggal/'.$this->tanggal));
    }

    public function test_ringkasan_memisahkan_berhasil_tidak_ada_jadwal_dan_gagal(): void
    {
        $this->sinkron();

        $sinkron = session('sinkron');
        $this->assertSame(5, $sinkron['jumlah_poli']);
        $this->assertSame(2, $sinkron['poli_berhasil']);
        $this->assertSame(3, $sinkron['jumlah_jadwal']);
        $this->assertSame([['kodepoli' => 'MAT', 'namapoli' => 'Mata']], $sinkron['tanpa_jadwal']);
        $this->assertSame(['OBG' => 'bpjs', 'THT' => 'koneksi'], collect($sinkron['gagal'])->pluck('jenis', 'kodepoli')->all());
        $this->assertSame('Gangguan', $sinkron['gagal'][0]['pesan']);
        $this->assertFalse($sinkron['dihentikan']);

        $this->get(route('antrol.monitoring'))
            ->assertOk()
            ->assertSeeInOrder(['Poli diproses', '5 / 5', 'Berhasil', '2', 'Tidak ada jadwal', '1', 'Gagal', '2', 'Jadwal tersimpan', '3'])
            ->assertSee('(1 gagal koneksi, 1 ditolak BPJS)')
            ->assertSeeInOrder(['OBG', 'ditolak BPJS', 'Gangguan', 'THT', 'gagal koneksi'])
            ->assertSee('Poli tanpa jadwal pada tanggal ini (1)')
            ->assertSee('2 berhasil (3 jadwal tersimpan), 1 tidak ada jadwal, 2 gagal.');
    }

    public function test_code_201_selain_no_content_tetap_dihitung_gagal(): void
    {
        $this->sinkron();

        $this->assertNotContains('OBG', collect(session('sinkron')['tanpa_jadwal'])->pluck('kodepoli')->all());
    }

    public function test_tidak_ada_jadwal_menghapus_jadwal_lama_sedangkan_poli_gagal_tidak_disentuh(): void
    {
        $lamaMat = JadwalUji::factory()->bpjs()->poli('MAT', 'Mata')->create(['kodedokter' => '55000']);
        $lamaMatHariLain = JadwalUji::factory()->bpjs()->poli('MAT', 'Mata')->create(['kodedokter' => '55001', 'hari' => now()->addDay()->dayOfWeekIso]);
        $lamaObg = JadwalUji::factory()->bpjs()->poli('OBG', 'Obgyn')->create(['kodedokter' => '56000']);
        $lamaTht = JadwalUji::factory()->bpjs()->poli('THT', 'THT')->create(['kodedokter' => '57000']);

        $this->sinkron();
        $this->sinkron();

        $this->assertModelMissing($lamaMat);
        $this->assertModelExists($lamaMatHariLain);
        $this->assertModelExists($lamaObg);
        $this->assertModelExists($lamaTht);
        $this->assertSame(2, JadwalUji::where('sumber', 'bpjs')->where('kodepoli', 'INT')->count());
        $this->assertSame(1, JadwalUji::where('sumber', 'bpjs')->where('kodepoli', 'ANA')->count());
    }

    public function test_setiap_panggilan_tercatat_di_log(): void
    {
        $this->sinkron();

        $endpoint = AntrolLog::where('arah', 'wsbpjs')->orderBy('id')->pluck('endpoint')->all();
        $this->assertSame(array_merge(['GET /ref/poli'], array_map(
            fn (string $kode) => "GET /jadwaldokter/kodepoli/{$kode}/tanggal/{$this->tanggal}",
            ['INT', 'ANA', 'MAT', 'OBG', 'THT'],
        )), $endpoint);
    }

    public function test_opsi_ganti_data_fiktif_menghapus_jadwal_fiktif(): void
    {
        $this->sinkron(['ganti_fiktif' => 1]);

        $this->assertSame(0, JadwalUji::where('sumber', 'fiktif')->count());
        $this->assertSame(3, JadwalUji::where('sumber', 'bpjs')->count());
        $this->assertSame(1, session('sinkron')['fiktif_dihapus']);
    }

    public function test_referensi_poli_gagal_tidak_mengubah_jadwal(): void
    {
        Http::swap(new Factory);
        $this->palsukanBpjs([self::BASE.'/ref/poli' => Http::response(['metadata' => ['code' => 201, 'message' => 'Gangguan']])]);

        $this->sinkron(['ganti_fiktif' => 1])
            ->assertSessionHas('peringatan', 'Referensi Poli gagal diambil: Gangguan. Jadwal tidak diubah.');

        $this->assertSame(0, JadwalUji::where('sumber', 'bpjs')->count());
        $this->assertSame(1, JadwalUji::where('sumber', 'fiktif')->count());
        Http::assertSentCount(1);
    }

    public function test_poli_yang_punya_jadwal_dicatat_dan_dipakai_sinkron_berikutnya(): void
    {
        $halaman = $this->get(route('antrol.monitoring'))->assertSee('Hanya poli yang pernah punya jadwal (0 poli)')->getContent();
        $this->assertMatchesRegularExpression('/value="berjadwal"\s+disabled/', $halaman);
        $this->assertMatchesRegularExpression('/value="semua"\s+checked/', $halaman);

        $this->sinkron();

        $this->assertSame(['ANA', 'INT'], PoliBerjadwal::orderBy('kodepoli')->pluck('kodepoli')->all());
        $halaman = $this->get(route('antrol.monitoring'))->assertSee('Hanya poli yang pernah punya jadwal (2 poli)')->getContent();
        $this->assertMatchesRegularExpression('/value="berjadwal"\s+checked/', $halaman);
        $this->assertDoesNotMatchRegularExpression('/value="semua"\s+checked/', $halaman);

        // Sinkron berikutnya tanpa memilih mode: default hanya poli berjadwal, tanpa Referensi Poli.
        AntrolLog::query()->delete();
        $this->post(route('antrol.monitoring.sinkron-jadwal'), ['tanggal' => $this->tanggal]);

        $this->assertSame('berjadwal', session('sinkron')['mode']);
        $this->assertSame([
            "GET /jadwaldokter/kodepoli/ANA/tanggal/{$this->tanggal}",
            "GET /jadwaldokter/kodepoli/INT/tanggal/{$this->tanggal}",
        ], AntrolLog::orderBy('id')->pluck('endpoint')->all());
    }

    public function test_mode_berjadwal_tanpa_daftar_poli_meminta_sinkron_semua_poli(): void
    {
        $this->sinkron(['mode' => 'berjadwal'])
            ->assertSessionHas('peringatan', 'Belum ada daftar poli yang pernah punya jadwal. Jalankan sinkron "Semua poli" terlebih dahulu.');

        Http::assertNothingSent();
    }

    public function test_sinkron_dihentikan_setelah_lima_kali_gagal_koneksi_berturut_turut(): void
    {
        Http::swap(new Factory);
        $poli = array_map(fn (int $i) => ['kdsubspesialis' => 'P0'.$i, 'nmsubspesialis' => 'Poli '.$i, 'kdpoli' => 'P0'.$i, 'nmpoli' => 'Poli '.$i], range(1, 8));
        $this->palsukanBpjs([
            self::BASE.'/ref/poli' => Http::response(['response' => $poli, 'metadata' => ['code' => 1, 'message' => 'OK']]),
            self::BASE.'/jadwaldokter/*' => Http::failedConnection('cURL error 6: Could not resolve host'),
        ]);

        $this->sinkron(['ganti_fiktif' => 1])
            ->assertSessionHas('peringatan', fn (string $pesan) => str_contains($pesan, 'Sinkron dihentikan setelah 5 kali gagal koneksi berturut-turut; 3 poli tidak diproses.'));

        $sinkron = session('sinkron');
        $this->assertTrue($sinkron['dihentikan']);
        $this->assertSame(3, $sinkron['tidak_diproses']);
        $this->assertCount(5, $sinkron['gagal']);
        Http::assertSentCount(6);
        $this->assertSame(1, JadwalUji::where('sumber', 'fiktif')->count());
    }

    public function test_jeda_antar_panggilan_mengikuti_config(): void
    {
        $this->assertSame(100, config('antrol.sinkron_jadwal.jeda_ms'));

        $this->sinkron();

        // Satu jeda sebelum setiap Referensi Jadwal Dokter (5 poli), sesudah Referensi Poli.
        Sleep::assertSequence(array_fill(0, 5, Sleep::for(100)->milliseconds()));
    }

    public function test_jeda_nol_tidak_menunggu(): void
    {
        config(['antrol.sinkron_jadwal.jeda_ms' => 0]);

        $this->sinkron();

        Sleep::assertNeverSlept();
    }

    public function test_pendaftaran_dan_monitoring_memakai_jadwal_hasil_sinkron_jika_ada(): void
    {
        $pasien = PasienUji::factory()->create();

        $this->get(route('antrol.pendaftaran', ['norm' => $pasien->norm]))
            ->assertSee('dr. Fiktif')
            ->assertSee('data fiktif');

        $this->sinkron();

        $this->get(route('antrol.pendaftaran', ['norm' => $pasien->norm]))
            ->assertOk()
            ->assertSee('hasil sinkron BPJS')
            ->assertSee('dr. Andi, Sp.PD')
            ->assertSee('dr. Bunga, Sp.A')
            ->assertDontSee('dr. Fiktif')
            ->assertDontSee('dr. Libur, Sp.PD');

        $this->get(route('antrol.monitoring'))
            ->assertOk()
            ->assertSee('hasil sinkron BPJS')
            ->assertSee('INT|33690|08:00-12:00')
            ->assertDontSee('INT|10001|')
            ->assertDontSee('dr. Libur, Sp.PD');
    }

    /** @param  array<string, mixed>  $tambahan */
    private function sinkron(array $tambahan = []): TestResponse
    {
        return $this->post(route('antrol.monitoring.sinkron-jadwal'), $tambahan + ['tanggal' => $this->tanggal, 'mode' => 'semua']);
    }

    /** @return array<string, mixed> */
    private function responsSinkron(): array
    {
        $ok = ['code' => 200, 'message' => 'OK'];
        $poli = fn (string $kode, string $nama) => ['nmpoli' => $nama, 'nmsubspesialis' => $nama, 'kdsubspesialis' => $kode, 'kdpoli' => $kode];

        return [
            self::BASE.'/ref/poli' => Http::response([
                'response' => [
                    $poli('INT', 'Penyakit Dalam'), $poli('ANA', 'Anak'), $poli('MAT', 'Mata'),
                    $poli('OBG', 'Obgyn'), $poli('THT', 'THT'), $poli('INT', 'Penyakit Dalam'),
                ],
                'metadata' => ['code' => 1, 'message' => 'OK'],
            ]),
            self::BASE.'/jadwaldokter/kodepoli/INT/tanggal/*' => Http::response([
                'response' => [
                    $this->jadwal('INT', 'PENYAKIT DALAM', 33690, 'dr. Andi, Sp.PD', '08:00-12:00', 25, 0),
                    $this->jadwal('INT', 'PENYAKIT DALAM', 33691, 'dr. Libur, Sp.PD', '13:00 - 16:00', 20, 1),
                ],
                'metadata' => $ok,
            ]),
            self::BASE.'/jadwaldokter/kodepoli/ANA/tanggal/*' => Http::response([
                'response' => [$this->jadwal('ANA', 'ANAK', 44100, 'dr. Bunga, Sp.A', '13:00-16:00', 15, 0)],
                'metadata' => $ok,
            ]),
            // Bentuk sesuai log BPJS development untuk poli tanpa jadwal.
            self::BASE.'/jadwaldokter/kodepoli/MAT/tanggal/*' => Http::response(['metadata' => ['code' => 201, 'message' => 'No Content']]),
            self::BASE.'/jadwaldokter/kodepoli/OBG/tanggal/*' => Http::response(['metadata' => ['code' => 201, 'message' => 'Gangguan']]),
            self::BASE.'/jadwaldokter/kodepoli/THT/tanggal/*' => Http::failedConnection('cURL error 6: Could not resolve host'),
        ];
    }

    /**
     * Satu baris jadwal, bentuk sesuai respons asli BPJS development.
     *
     * @return array<string, mixed>
     */
    private function jadwal(string $kode, string $nama, int $kodedokter, string $namadokter, string $jam, int $kapasitas, int $libur): array
    {
        return [
            'kodesubspesialis' => $kode, 'hari' => now()->dayOfWeekIso, 'kapasitaspasien' => $kapasitas, 'libur' => $libur,
            'namahari' => 'HARI', 'jadwal' => $jam, 'namasubspesialis' => $nama, 'namadokter' => $namadokter,
            'kodepoli' => $kode, 'namapoli' => $nama, 'kodedokter' => $kodedokter,
        ];
    }
}
