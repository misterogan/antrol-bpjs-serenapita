<?php

namespace Database\Factories\Antrol;

use App\Models\Antrol\AntreanFarmasi;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AntreanFarmasi> */
class AntreanFarmasiFactory extends Factory
{
    protected $model = AntreanFarmasi::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $nomor = fake()->unique()->numberBetween(1, 999);

        return [
            'kodebooking' => now()->format('Ymd').'INT'.str_pad((string) $nomor, 3, '0', STR_PAD_LEFT),
            'tanggal' => now()->toDateString(),
            'nomorantrean' => $nomor,
            'jenisresep' => 'Racikan',
            'status' => AntreanFarmasi::MENUNGGU,
            'keterangan' => '',
            'sumber' => AntreanFarmasi::SUMBER_ONSITE,
            'nama' => strtoupper(fake()->name()),
            'norm' => (string) fake()->numerify('00####'),
        ];
    }

    public function status(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
