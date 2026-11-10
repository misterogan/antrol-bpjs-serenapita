<?php

namespace Tests\Feature\Antrol;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PendaftaranFingerprintTest extends AntrolTestCase
{
    public function test_cek_poli_fingerprint_selalu_memanggil_bpjs_dan_menampilkan_tabel(): void
    {
        $this->palsukanBpjs();
        Cache::put('antrol.poli_fp', ['LAMA'], now()->addHour());

        $this->post(route('antrol.pendaftaran.poli-fp.cek'))->assertRedirect(route('antrol.pendaftaran.poli-fp'));
        $this->post(route('antrol.pendaftaran.poli-fp.cek'));

        Http::assertSentCount(2);
        $this->assertSame([self::POLI_FP], Cache::get('antrol.poli_fp'));
        $this->get(route('antrol.pendaftaran.poli-fp'))
            ->assertOk()
            ->assertSeeInOrder(['code 1', 'OK', '1</b> poli', 'No', 'Kode Poli', 'Nama Poli', 'Kode Subspesialis', 'Nama Subspesialis', 'JAN', 'JANTUNG', 'JAN', 'Jantung'], false)
            ->assertSeeInOrder(['Lihat request &amp; response BPJS', 'Response (diterima tanpa enkripsi dari BPJS)'], false);
    }

    public function test_cek_poli_fingerprint_gagal_tidak_mengubah_cache(): void
    {
        $this->palsukanBpjs([self::BASE.'/ref/poli/fp' => Http::response(['metadata' => ['code' => 201, 'message' => 'Gangguan']])]);
        Cache::put('antrol.poli_fp', ['LAMA'], now()->addHour());

        $this->post(route('antrol.pendaftaran.poli-fp.cek'));

        $this->assertSame(['LAMA'], Cache::get('antrol.poli_fp'));
        $this->get(route('antrol.pendaftaran.poli-fp'))->assertSee('code 201')->assertSee('Gangguan')->assertSee('Tidak ada data poli pada respons.');
    }

    public function test_cek_peserta_fingerprint_menampilkan_kartu_ringkas(): void
    {
        $this->palsukanBpjs([self::BASE.'/ref/pasien/fp/*' => Http::response([
            'response' => ['nomorkartu' => '0001234567890', 'nik' => '3212345678987654', 'tgllahir' => 631152000000, 'daftarfp' => 1],
            'metadata' => ['message' => 'Ok', 'code' => 200],
        ])]);

        $this->post(route('antrol.pendaftaran.peserta-fp.cek'), ['jenis' => 'noka', 'nomor' => '0001234567890'])
            ->assertRedirect(route('antrol.pendaftaran.peserta-fp'));

        Http::assertSent(fn ($r) => str_contains($r->url(), '/ref/pasien/fp/identitas/noka/noidentitas/0001234567890'));
        $this->get(route('antrol.pendaftaran.peserta-fp'))
            ->assertOk()
            ->assertSeeInOrder(['code 200', 'NIK', '3212345678987654', 'Nomor kartu', '0001234567890', 'Tanggal lahir', '01-01-1990', 'Status fingerprint', 'daftarfp = <b>1</b> (Sudah terdaftar fingerprint)', 'Lihat request &amp; response BPJS'], false);
    }

    public function test_peserta_tidak_terdaftar_ditampilkan_sebagai_hasil(): void
    {
        $this->palsukanBpjs([self::BASE.'/ref/pasien/fp/*' => Http::response(['metadata' => ['message' => 'Peserta tidak terdaftar', 'code' => 0]])]);

        $this->post(route('antrol.pendaftaran.peserta-fp.cek'), ['jenis' => 'nik', 'nomor' => '3212345678987654']);

        $halaman = $this->get(route('antrol.pendaftaran.peserta-fp'))
            ->assertSee('code 0')
            ->assertSee('Peserta tidak terdaftar')
            ->assertSee('dicek dengan NIK 3212345678987654')
            ->getContent();
        $this->assertStringContainsString('<section class="panel hasil "', $halaman);
    }

    public function test_validasi_nomor_peserta(): void
    {
        Http::fake();

        $this->post(route('antrol.pendaftaran.peserta-fp.cek'), ['jenis' => 'nik', 'nomor' => '123'])
            ->assertSessionHasErrors(['nomor' => 'NIK harus 16 digit angka.']);
        $this->post(route('antrol.pendaftaran.peserta-fp.cek'), ['jenis' => 'noka', 'nomor' => '12345678901234'])
            ->assertSessionHasErrors(['nomor' => 'Nomor kartu harus 13 digit angka.']);

        Http::assertNothingSent();
    }

    public function test_bpjs_tidak_bisa_dihubungi_tampil_gagal(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([self::BASE.'/*' => Http::failedConnection('cURL error 6: Could not resolve host')]);

        $this->post(route('antrol.pendaftaran.peserta-fp.cek'), ['jenis' => 'noka', 'nomor' => '0001234567890']);

        $this->get(route('antrol.pendaftaran.peserta-fp'))->assertSee('cURL error 6: Could not resolve host')->assertSee('panel hasil gagal', false);
    }

    public function test_tab_pendaftaran_simulasi_tetap_memakai_halaman_lama(): void
    {
        $this->palsukanBpjs();

        $this->get(route('antrol.pendaftaran'))
            ->assertOk()
            ->assertSeeInOrder(['Cek Poli Fingerprint', 'Cek Peserta Fingerprint', 'Pendaftaran (simulasi)', 'Pendaftaran rawat jalan (simulasi)'])
            ->assertSee('class="aktif">Pendaftaran (simulasi)', false);
    }
}
