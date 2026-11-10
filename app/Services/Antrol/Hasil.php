<?php

namespace App\Services\Antrol;

/** Bentuk respons standar katalog: {response?, metadata{message, code}}. */
class Hasil
{
    public static function ok(?array $response = null): array
    {
        $out = [];
        if ($response !== null) {
            $out['response'] = $response;
        }
        $out['metadata'] = ['message' => 'Ok', 'code' => 200];

        return $out;
    }

    public static function gagal(string $pesan, int $code = 201): array
    {
        return ['metadata' => ['message' => $pesan, 'code' => $code]];
    }

    public static function kode(array $hasil): int
    {
        return (int) ($hasil['metadata']['code'] ?? 200);
    }
}
