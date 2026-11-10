<?php

namespace Database\Factories\Antrol;

use App\Models\Antrol\Resep;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Resep> */
class ResepFactory extends Factory
{
    protected $model = Resep::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'kodebooking' => now()->format('Ymd').'INT'.str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'jenisresep' => 'Racikan',
            'norekammedis' => (string) fake()->unique()->numerify('00####'),
            'namapasien' => strtoupper(fake()->name()),
            'nomorkartu' => fake()->numerify('000#########'),
            'kodepoli' => 'INT',
            'tanggal' => now()->toDateString(),
            'sumber' => Resep::SUMBER_VENDOR,
        ];
    }

    public function tanpaNama(): static
    {
        return $this->state(fn () => ['namapasien' => null]);
    }
}
