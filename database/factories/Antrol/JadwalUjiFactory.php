<?php

namespace Database\Factories\Antrol;

use App\Models\Antrol\JadwalUji;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JadwalUji> */
class JadwalUjiFactory extends Factory
{
    protected $model = JadwalUji::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'kodepoli' => 'INT',
            'namapoli' => 'PENYAKIT DALAM',
            'kodedokter' => (string) fake()->unique()->numberBetween(10000, 99999),
            'namadokter' => 'dr. '.fake()->name(),
            'hari' => now()->dayOfWeekIso,
            'jampraktek' => '08:00-12:00',
            'kuota_jkn' => 30,
            'kuota_nonjkn' => 10,
        ];
    }

    /** Jadwal untuk poli tertentu. */
    public function poli(string $kodepoli, string $namapoli): static
    {
        return $this->state(fn () => ['kodepoli' => $kodepoli, 'namapoli' => $namapoli]);
    }

    /** Jadwal hasil sinkron Referensi Jadwal Dokter BPJS. */
    public function bpjs(): static
    {
        return $this->state(fn () => ['sumber' => JadwalUji::SUMBER_BPJS, 'disinkron_at' => now()]);
    }
}
