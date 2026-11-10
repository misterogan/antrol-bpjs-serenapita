<?php

namespace Database\Factories\Antrol;

use App\Models\Antrol\AntreanPoliUji;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AntreanPoliUji> */
class AntreanPoliUjiFactory extends Factory
{
    protected $model = AntreanPoliUji::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $angka = fake()->unique()->numberBetween(1, 999);

        return [
            'kodebooking' => now()->format('Ymd').'INT'.str_pad((string) $angka, 3, '0', STR_PAD_LEFT),
            'tanggal' => now()->toDateString(),
            'status' => 'terdaftar',
            'norm' => (string) fake()->numerify('00####'),
            'nama' => strtoupper(fake()->name()),
            'nomorkartu' => (string) fake()->numerify('0001#########'),
            'nik' => (string) fake()->numerify('3171############'),
            'jenispasien' => 'JKN',
            'kodepoli' => 'INT',
            'namapoli' => 'PENYAKIT DALAM',
            'kodedokter' => '12345',
            'namadokter' => 'dr. Uji',
            'jampraktek' => '08:00-12:00',
            'nomorantrean' => 'INT-'.str_pad((string) $angka, 3, '0', STR_PAD_LEFT),
            'angkaantrean' => $angka,
        ];
    }

    /** Pelayanan poli selesai dengan jenis resep tertentu (task ID 5). */
    public function selesai(string $jenisresep = 'Non Racikan'): static
    {
        return $this->state(fn () => [
            'status' => 'selesai',
            'checkin' => true,
            'taskid3_at' => now()->subMinutes(30),
            'taskid4_at' => now()->subMinutes(15),
            'taskid5_at' => now(),
            'jenisresep' => $jenisresep,
        ]);
    }
}
