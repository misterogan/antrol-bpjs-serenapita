<?php

namespace Tests\Feature\Antrol;

use App\Models\Antrol\AntreanFarmasi;
use App\Models\Antrol\AntrolLog;
use App\Models\Antrol\Resep;

/** Istilah yang tampil di halaman untuk UAT, dan loading detak jantung. */
class TampilanIstilahTest extends AntrolTestCase
{
    public function test_tidak_ada_kata_vendor_atau_nama_perusahaan_yang_tampil(): void
    {
        $this->palsukanBpjs();
        config(['antrol.sumber_data' => 'bpjs', 'antrol.wsrs.token_mode' => 'vendor']);
        Resep::factory()->create(['kodebooking' => 'KB-SIMRS', 'namapasien' => 'PASIEN SIMRS']);
        AntreanFarmasi::factory()->create(['sumber' => AntreanFarmasi::SUMBER_WSRS]);
        AntrolLog::catat('vendor', 'POST /jkn/sisa-antrian', ['headers' => ['x-token' => 'abcd...yz']], ['hasil' => 'gagal', 'alasan' => 'SIMRS tidak dapat dihubungi atau timeout.', 'http_status' => null], null);
        AntrolLog::catat('internal', 'POST jkn/internal/resep', ['body' => []], ['metadata' => ['message' => 'Ok', 'code' => 200]], 200);

        $rute = ['antrol.beranda', 'antrol.pendaftaran.poli-fp', 'antrol.pendaftaran.peserta-fp', 'antrol.pendaftaran', 'antrol.farmasi', 'antrol.monitoring',
            'antrol.log', 'antrol.alat-uji', 'antrol.poli', 'antrol.data-uji', 'antrol.wsbpjs', 'antrol.api-docs'];
        foreach ($rute as $r) {
            $teks = $this->teksTampil($this->get(route($r))->assertOk()->getContent());
            $this->assertDoesNotMatchRegularExpression('/vendor|dhealth|enigma/i', $teks, "Halaman {$r} masih menampilkan istilah vendor/nama perusahaan.");
        }
        $teks = $this->teksTampil($this->get(route('antrol.beranda', ['hal' => 2]))->getContent());
        $this->assertDoesNotMatchRegularExpression('/vendor|dhealth|enigma/i', $teks);
    }

    public function test_label_token_dan_sumber_data_tidak_tampil_di_header(): void
    {
        config(['antrol.wsrs.token_mode' => 'vendor']);
        $this->get(route('antrol.beranda'))->assertDontSee('Token WSRS')->assertDontSee('Sumber data')->assertSee('Modul Antrean Online');

        config(['antrol.wsrs.token_mode' => 'lokal']);
        $this->get(route('antrol.alat-uji'))->assertDontSee('Token WSRS')->assertDontSee('Sumber data');
    }

    public function test_filter_dan_label_arah_di_log(): void
    {
        AntrolLog::catat('vendor', 'POST /jkn/sisa-antrian', [], ['hasil' => 'sah'], 201);
        AntrolLog::catat('internal', 'POST jkn/internal/resep', [], ['metadata' => ['code' => 200, 'message' => 'Ok']], 200);

        $this->get(route('antrol.log'))
            ->assertSee('<option value="vendor" >Validasi token SIMRS</option>', false)
            ->assertSee('<option value="internal" >Kiriman dari SIMRS</option>', false)
            ->assertSee('VALIDASI TOKEN SIMRS')
            ->assertSee('KIRIMAN DARI SIMRS');
    }

    public function test_loading_detak_jantung_ada_di_setiap_halaman(): void
    {
        $this->get(route('antrol.beranda'))
            ->assertSee('id="antrol-memuat" class="memuat" role="status"', false)
            ->assertSee('class="memuat-garis"', false)
            ->assertSee('class="memuat-jantung"', false)
            ->assertSee('Memproses data...')
            ->assertSee('window.antrolLoading', false)
            ->assertSee("tampil('Membuka halaman...', 15000)", false);

        $this->get(route('antrol.monitoring'))->assertSee('data-pesan-memuat="Sinkron jadwal dokter dari BPJS, bisa memakan waktu beberapa menit..."', false);
        $this->get(route('antrol.farmasi'))->assertSee("window.antrolLoading.tampil('Membuat antrean farmasi...')", false);
    }

    /** Teks yang terlihat pengguna: tanpa script, style, komentar, dan atribut tag. */
    private function teksTampil(string $html): string
    {
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html);
        $html = preg_replace('#<!--.*?-->#s', ' ', $html);
        $judul = [];
        preg_match_all('#\s(?:title|placeholder|aria-label|alt)="([^"]*)"#i', $html, $judul);

        return html_entity_decode(strip_tags($html)).' '.implode(' ', $judul[1]);
    }
}
