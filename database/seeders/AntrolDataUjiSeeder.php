<?php

namespace Database\Seeders;

use App\Models\Antrol\JadwalUji;
use App\Models\Antrol\PasienUji;
use Illuminate\Database\Seeder;

/**
 * Contoh pasien & jadwal dokter untuk demo alur pendaftaran → poli → farmasi.
 *
 * PENTING: kode poli & kode dokter harus diganti dengan kode HFIS RS di BPJS development
 * jika mode simulasi dipakai, karena BPJS memvalidasinya saat Tambah Antrean.
 * Nomor kartu & NIK pasien juga harus peserta uji yang valid di BPJS development.
 *
 * php artisan db:seed --class=AntrolDataUjiSeeder
 */
class AntrolDataUjiSeeder extends Seeder
{
    public function run(): void
    {
        $poli = [
            ['INT', 'PENYAKIT DALAM', '10001', 'dr. Andi Wijaya, Sp.PD', '08:00-12:00'],
            ['INT', 'PENYAKIT DALAM', '10002', 'dr. Rina Kartika, Sp.PD', '13:00-16:00'],
            ['JAN', 'JANTUNG DAN PEMBULUH DARAH', '10003', 'dr. Bayu Pratama, Sp.JP', '08:00-11:00'],
            ['ANA', 'ANAK', '10004', 'dr. Sari Lestari, Sp.A', '09:00-12:00'],
            ['MAT', 'MATA', '10005', 'dr. Hendra Gunawan, Sp.M', '10:00-14:00'],
            ['SAR', 'SARAF', '10006', 'dr. Dewi Anggraini, Sp.N', '08:00-12:00'],
        ];

        foreach (range(1, 7) as $hari) {
            foreach ($poli as [$kodepoli, $namapoli, $kodedokter, $namadokter, $jam]) {
                JadwalUji::updateOrCreate(
                    ['kodepoli' => $kodepoli, 'kodedokter' => $kodedokter, 'hari' => $hari, 'jampraktek' => $jam],
                    ['namapoli' => $namapoli, 'namadokter' => $namadokter, 'kuota_jkn' => 30, 'kuota_nonjkn' => 10],
                );
            }
        }

        $pasien = [
            ['000101', 'BUDI SANTOSO', '0001234567890', '3171010101800001', '081200000001', '1980-01-01'],
            ['000102', 'SITI AMINAH', '0001234567891', '3171014505850002', '081200000002', '1985-05-05'],
            ['000103', 'AHMAD FAUZI', '0001234567892', '3171011210900003', '081200000003', '1990-10-12'],
            ['000104', 'MARIA ULFA', '0001234567893', '3171016703950004', '081200000004', '1995-03-27'],
            ['000105', 'JOKO SUSILO', null, '3171010808700005', '081200000005', '1970-08-08'],
        ];

        foreach ($pasien as [$norm, $nama, $noka, $nik, $hp, $lahir]) {
            PasienUji::updateOrCreate(
                ['norm' => $norm],
                ['nama' => $nama, 'nomorkartu' => $noka, 'nik' => $nik, 'nohp' => $hp, 'tanggal_lahir' => $lahir],
            );
        }
    }
}
