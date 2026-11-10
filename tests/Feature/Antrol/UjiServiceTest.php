<?php

namespace Tests\Feature\Antrol;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use LZCompressor\LZString;

class UjiServiceTest extends AntrolTestCase
{
    public function test_panel_hasil_menampilkan_response_terenkripsi_yang_dipotong_beserta_panjang_aslinya(): void
    {
        $this->freezeSecond();
        $daftarPoli = collect(range(1, 40))->map(fn (int $i) => [
            'kodepoli' => 'P'.$i, 'namapoli' => 'POLI UJI NOMOR '.$i, 'kodesubspesialis' => 'S'.$i, 'namasubspesialis' => 'Sub '.$i,
        ])->all();
        $terenkripsi = $this->enkrip($daftarPoli, (string) time());
        $this->assertGreaterThan(500, strlen($terenkripsi));

        $this->palsukanBpjs([self::BASE.'/ref/poli/fp' => Http::response([
            'response' => $terenkripsi,
            'metadata' => ['code' => 1, 'message' => 'OK'],
        ])]);

        $this->post(route('antrol.poli-fp'))->assertRedirect(route('antrol.wsbpjs'));

        $this->get(route('antrol.wsbpjs'))
            ->assertOk()
            ->assertSeeInOrder(['Response asli dari BPJS (terenkripsi)', 'Response (sudah didekripsi'])
            ->assertSee(substr($terenkripsi, 0, 500).'...')
            ->assertDontSee(substr($terenkripsi, 0, 501))
            ->assertSee('Panjang asli '.number_format(strlen($terenkripsi), 0, ',', '.').' karakter, ditampilkan 500 karakter pertama.')
            ->assertSee('POLI UJI NOMOR 40');
    }

    public function test_response_tidak_terenkripsi_diberi_keterangan(): void
    {
        $this->palsukanBpjs();

        $this->post(route('antrol.poli-fp'));

        $this->get(route('antrol.wsbpjs'))
            ->assertSee('Tidak ada field "response" terenkripsi pada body yang diterima.', false);
    }

    public function test_judul_response_terenkripsi_tetap_sudah_didekripsi(): void
    {
        $this->freezeSecond();
        $this->palsukanBpjs([self::BASE.'/ref/poli/fp' => Http::response([
            'response' => $this->enkrip([['kodepoli' => 'INT', 'namapoli' => 'PENYAKIT DALAM']], (string) time()),
            'metadata' => ['code' => 1, 'message' => 'OK'],
        ])]);

        $this->post(route('antrol.poli-fp'));

        $this->get(route('antrol.wsbpjs'))
            ->assertSee('<label>Response (sudah didekripsi &amp; didekompresi)</label>', false)
            ->assertDontSee('Response (diterima tanpa enkripsi dari BPJS)');
    }

    public function test_judul_response_tidak_terenkripsi_menyebut_diterima_tanpa_enkripsi(): void
    {
        $this->palsukanBpjs();

        $this->post(route('antrol.poli-fp'));

        $this->get(route('antrol.wsbpjs'))
            ->assertSee('<label>Response (diterima tanpa enkripsi dari BPJS)</label>', false)
            ->assertDontSee('Response (sudah didekripsi');
    }

    public function test_antrean_per_kode_booking_menampilkan_request_respons_terenkripsi_dan_hasil_dekripsi(): void
    {
        $this->freezeSecond();
        $antrean = [[
            'kodebooking' => '20261107INT001', 'tanggal' => '2026-11-07', 'kodepoli' => 'INT', 'kodedokter' => 33690,
            'jampraktek' => '08:00-12:00', 'nik' => '3212345678987654', 'nokapst' => '0001234567890', 'nohp' => '085635228888',
            'norekammedis' => '000101', 'jeniskunjungan' => 1, 'nomorreferensi' => 'RUJ001', 'sumberdata' => 'Bridging Antrean',
            'ispeserta' => 1, 'noantrean' => 'INT-001', 'estimasidilayani' => 1794016800000, 'createdtime' => 1794009600000, 'status' => 'Belum dilayani',
        ]];
        $terenkripsi = $this->enkrip($antrean, (string) time());
        $this->palsukanBpjs([self::BASE.'/antrean/pendaftaran/kodebooking/*' => Http::response([
            'response' => $terenkripsi,
            'metadata' => ['code' => 200, 'message' => 'Ok'],
        ])]);

        $this->post(route('antrol.antrean-kodebooking'), ['kodebooking' => '20261107INT001'])->assertRedirect(route('antrol.wsbpjs'));

        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r->url() === self::BASE.'/antrean/pendaftaran/kodebooking/20261107INT001');
        $this->get(route('antrol.wsbpjs'))
            ->assertOk()
            ->assertSeeInOrder([
                'Antrean Per Kode Booking', 'code 200',
                'Request', 'GET '.self::BASE.'/antrean/pendaftaran/kodebooking/20261107INT001',
                'Metadata', 'Response asli dari BPJS (terenkripsi)', substr($terenkripsi, 0, 60),
                'Response (sudah didekripsi', 'INT-001', 'Belum dilayani',
            ]);
    }

    public function test_list_waktu_task_id_mengirim_body_kode_booking_dan_menampilkan_hasil_dekripsi(): void
    {
        $this->freezeSecond();
        $task = [
            ['wakturs' => '07-11-2026 08:00:00 WIB', 'waktu' => '07-11-2026 08:00:00 WIB', 'taskname' => 'mulai waktu tunggu admisi', 'taskid' => 1, 'kodebooking' => '20261107INT001'],
            ['wakturs' => '07-11-2026 08:20:00 WIB', 'waktu' => '07-11-2026 08:20:00 WIB', 'taskname' => 'akhir waktu layan poli', 'taskid' => 5, 'kodebooking' => '20261107INT001'],
        ];
        $terenkripsi = $this->enkrip($task, (string) time());
        $this->palsukanBpjs([self::BASE.'/antrean/getlisttask' => Http::response([
            'response' => $terenkripsi,
            'metadata' => ['code' => 200, 'message' => 'Ok'],
        ])]);

        $this->post(route('antrol.list-task'), ['kodebooking' => '20261107INT001']);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === self::BASE.'/antrean/getlisttask'
            && $r->data() === ['kodebooking' => '20261107INT001']);
        $this->get(route('antrol.wsbpjs'))
            ->assertOk()
            ->assertSeeInOrder([
                'List Waktu Task ID', 'POST '.self::BASE.'/antrean/getlisttask', '&quot;kodebooking&quot;: &quot;20261107INT001&quot;',
                'Response asli dari BPJS (terenkripsi)', 'Response (sudah didekripsi', 'mulai waktu tunggu admisi', 'akhir waktu layan poli',
            ], false);
    }

    public function test_jawaban_tidak_ditemukan_ditampilkan_apa_adanya(): void
    {
        $this->palsukanBpjs([self::BASE.'/antrean/getlisttask' => Http::response(['metadata' => ['code' => 201, 'message' => 'Data tidak ditemukan']])]);

        $this->post(route('antrol.list-task'), ['kodebooking' => 'TIDAKADA']);

        $this->get(route('antrol.wsbpjs'))
            ->assertSee('code 201')
            ->assertSee('Data tidak ditemukan')
            ->assertSee('Response (diterima tanpa enkripsi dari BPJS)');
    }

    public function test_kode_booking_dengan_karakter_path_ditolak_tanpa_memanggil_bpjs(): void
    {
        $this->palsukanBpjs();

        foreach (['antrol.antrean-kodebooking', 'antrol.list-task'] as $route) {
            $this->post(route($route), ['kodebooking' => '../ref/poli'])
                ->assertSessionHasErrors(['kodebooking' => 'Kode booking hanya boleh berisi huruf, angka, tanda hubung (-), dan garis bawah (_).']);
        }

        Http::assertNothingSent();
    }

    /**
     * Kebalikan dari AntrolClient::dekrip(): LZString lalu AES-256-CBC.
     *
     * @param  array<int, array<string, mixed>>  $data
     */
    private function enkrip(array $data, string $timestamp): string
    {
        $keyHash = hex2bin(hash('sha256', 'uji'.'rahasia-uji'.$timestamp));
        $terkompresi = LZString::compressToEncodedURIComponent(json_encode($data));

        return base64_encode(openssl_encrypt($terkompresi, 'AES-256-CBC', $keyHash, OPENSSL_RAW_DATA, substr($keyHash, 0, 16)));
    }
}
