<?php

namespace Database\Factories\Antrol;

use App\Models\Antrol\PasienUji;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PasienUji> */
class PasienUjiFactory extends Factory
{
    protected $model = PasienUji::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'norm' => (string) fake()->unique()->numerify('00####'),
            'nomorkartu' => (string) fake()->unique()->numerify('0001#########'),
            'nik' => (string) fake()->unique()->numerify('3171############'),
            'nama' => strtoupper(fake()->name()),
            'nohp' => fake()->numerify('08##########'),
            'tanggal_lahir' => fake()->date('Y-m-d', '-20 years'),
        ];
    }
}
