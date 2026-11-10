<?php

namespace App\Http\Middleware;

use App\Services\Antrol\Hasil;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Semua route antrean farmasi (web, WSRS, API internal resep/antrean farmasi) menjawab 404
 * jika ANTROL_FITUR_FARMASI=false, karena antrean farmasi ditangani SIMRS vendor. Kode tetap ada.
 * Parameter "json" untuk route API: jawaban 404 memakai format metadata.
 */
class AntrolFiturFarmasi
{
    public const PESAN = 'Antrean farmasi dilayani SIMRS vendor (ANTROL_FITUR_FARMASI=false), bukan modul antrol';

    public static function aktif(): bool
    {
        return (bool) config('antrol.fitur_farmasi', false);
    }

    public function handle(Request $request, Closure $next, ?string $format = null): Response
    {
        if (! self::aktif()) {
            if ($format === 'json') {
                return response()->json(Hasil::gagal(self::PESAN, 404), 404);
            }

            abort(404);
        }

        return $next($request);
    }
}
