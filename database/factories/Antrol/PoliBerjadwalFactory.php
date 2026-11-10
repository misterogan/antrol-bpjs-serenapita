<?php

namespace Database\Factories\Antrol;

use App\Models\Antrol\PoliBerjadwal;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PoliBerjadwal> */
class PoliBerjadwalFactory extends Factory
{
    protected $model = PoliBerjadwal::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'kodepoli' => strtoupper(fake()->unique()->lexify('???')),
            'namapoli' => strtoupper(fake()->words(2, true)),
            'tanggal_jadwal_terakhir' => now()->toDateString(),
        ];
    }
}
