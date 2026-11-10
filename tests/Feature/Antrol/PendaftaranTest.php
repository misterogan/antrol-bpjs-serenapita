<?php

namespace Tests\Feature\Antrol;

use App\Models\Antrol\AntreanPoliUji;
use App\Models\Antrol\JadwalUji;
use App\Models\Antrol\PasienUji;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class PendaftaranTest extends AntrolTestCase
{
    private PasienUji $pasien;

    private JadwalUji $jadwalFp;

    private JadwalUji $jadwalBiasa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->palsukanBpjs();

        $this->pasien = PasienUji::factory()->create(['nomorkartu' => '0001234567890']);
        $this->jadwalFp = JadwalUji::factory()->poli(self::POLI_FP, 'JANTUNG')->create();
        $this->jadwalBiasa = JadwalUji::factory()->poli('INT', 'PENYAKIT DALAM')->create();
    }

    public function test_memilih_pasien_menampilkan_status_referensi_pasien_fingerprint(): void
    {
        $this->get(route('antrol.pendaftaran', ['norm' => $this->pasien->norm]))
            ->assertOk()
            ->assertSee('Referensi Pasien Fingerprint')
            ->assertSee('daftarfp');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/ref/pasien/fp/identitas/noka/noidentitas/0001234567890'));
    }

    public function test_pendaftaran_ke_poli_fingerprint_menampilkan_pesan_sebelum_disimpan(): void
    {
        $this->post(route('antrol.pendaftaran.periksa'), $this->isian($this->jadwalFp))
            ->assertOk()
            ->assertSee('Konfirmasi pendaftaran')
            ->assertSee(config('antrol.pesan_fingerprint'))
            ->assertSee('name="konfirmasi_fp"', false);

        $this->assertDatabaseCount('antrol_poli_uji', 0);
    }

    public function test_pendaftaran_ke_poli_biasa_tidak_menampilkan_pesan_fingerprint(): void
    {
        $this->post(route('antrol.pendaftaran.periksa'), $this->isian($this->jadwalBiasa))
            ->assertOk()
            ->assertSee('Konfirmasi pendaftaran')
            ->assertDontSee(config('antrol.pesan_fingerprint'));
    }

    public function test_poli_fingerprint_tidak_bisa_disimpan_tanpa_konfirmasi_pesan(): void
    {
        $this->post(route('antrol.pendaftaran.simpan'), $this->isian($this->jadwalFp))
            ->assertRedirect()
            ->assertSessionHasErrors('pendaftaran');

        $this->assertDatabaseCount('antrol_poli_uji', 0);
    }

    public function test_simpan_menghasilkan_kode_booking_dan_nomor_antrean_berurutan(): void
    {
        $this->post(route('antrol.pendaftaran.simpan'), $this->isian($this->jadwalFp) + ['konfirmasi_fp' => 1])
            ->assertRedirect()
            ->assertSessionHas('terdaftar');

        $pasien2 = PasienUji::factory()->create();
        $this->post(route('antrol.pendaftaran.simpan'), ['norm' => $pasien2->norm] + $this->isian($this->jadwalFp) + ['konfirmasi_fp' => 1]);

        $antrean = AntreanPoliUji::orderBy('id')->get();
        $this->assertCount(2, $antrean);
        $this->assertSame('JAN-001', $antrean[0]->nomorantrean);
        $this->assertSame('JAN-002', $antrean[1]->nomorantrean);
        $this->assertSame(now()->format('Ymd').'JAN001', $antrean[0]->kodebooking);
        $this->assertTrue($antrean[0]->wajib_fp);
        $this->assertSame('terdaftar', $antrean[0]->status);

        // Mode simulasi mati: tidak ada Tambah Antrean ke BPJS.
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/antrean/add'));
    }

    public function test_pasien_tidak_bisa_terdaftar_dua_kali_di_poli_yang_sama(): void
    {
        $this->post(route('antrol.pendaftaran.simpan'), $this->isian($this->jadwalBiasa));
        $this->post(route('antrol.pendaftaran.periksa'), $this->isian($this->jadwalBiasa))
            ->assertSessionHasErrors('pendaftaran');

        $this->assertDatabaseCount('antrol_poli_uji', 1);
    }

    public function test_mode_simulasi_mengirim_tambah_antrean_ke_bpjs(): void
    {
        config(['antrol.mode_simulasi' => true]);

        $this->post(route('antrol.pendaftaran.simpan'), $this->isian($this->jadwalBiasa) + ['nomorreferensi' => 'RUJ001'])
            ->assertSessionHas('bpjs');

        Http::assertSent(function (Request $r) {
            return str_ends_with($r->url(), '/antrean/add')
                && $r['kodebooking'] === now()->format('Ymd').'INT001'
                && $r['nomorkartu'] === '0001234567890'
                && $r['nomorreferensi'] === 'RUJ001'
                && $r['kuotajkn'] === 30
                && $r['sisakuotajkn'] === 29;
        });
        $this->assertNotNull(AntreanPoliUji::first()->dikirim_bpjs_at);
        $this->get(route('antrol.pendaftaran'))->assertSee('MODE SIMULASI - DEVELOPMENT');
    }

    public function test_referensi_poli_fingerprint_berhasil_disimpan_di_cache(): void
    {
        $this->get(route('antrol.pendaftaran'))->assertOk()->assertDontSee('Referensi Poli Fingerprint dari BPJS gagal dibaca');
        $this->get(route('antrol.pendaftaran'))->assertOk();

        Http::assertSentCount(1);
    }

    public function test_referensi_poli_fingerprint_gagal_ditampilkan_dan_tidak_dicoba_ulang_setiap_halaman(): void
    {
        Http::swap(new Factory);
        $this->palsukanBpjs([self::BASE.'/ref/poli/fp' => Http::response(['metadata' => ['code' => 201, 'message' => 'Gangguan']])]);

        $this->get(route('antrol.pendaftaran'))->assertSee('Referensi Poli Fingerprint dari BPJS gagal dibaca');
        $this->get(route('antrol.pendaftaran'))->assertSee('Referensi Poli Fingerprint dari BPJS gagal dibaca');
        Http::assertSentCount(1);

        // Setelah 1 menit dicoba lagi.
        $this->travel(61)->seconds();
        $this->get(route('antrol.pendaftaran'));
        Http::assertSentCount(2);
    }

    public function test_pasien_baru_dapat_disimpan_dan_langsung_dipilih(): void
    {
        $this->post(route('antrol.pendaftaran.pasien'), ['norm' => '009999', 'nama' => 'Pasien Baru', 'nomorkartu' => '0009999999999'])
            ->assertRedirect(route('antrol.pendaftaran', ['norm' => '009999']));

        $this->assertDatabaseHas('antrol_pasien_uji', ['norm' => '009999', 'nama' => 'PASIEN BARU']);
    }

    public function test_pesan_validasi_berbahasa_indonesia(): void
    {
        $this->post(route('antrol.pendaftaran.pasien'), ['norm' => '1', 'nama' => 'X', 'nik' => '123'])
            ->assertSessionHasErrors(['nik' => 'NIK harus 16 digit angka.']);
    }

    /** @return array<string, mixed> */
    private function isian(JadwalUji $jadwal): array
    {
        return [
            'norm' => $this->pasien->norm,
            'jadwal_id' => (string) $jadwal->id,
            'tanggal' => now()->toDateString(),
            'jenispasien' => 'JKN',
            'jeniskunjungan' => 1,
        ];
    }
}
