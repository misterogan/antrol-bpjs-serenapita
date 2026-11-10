<?php

namespace App\Http\Middleware;

use App\Services\Antrol\Hasil;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route WSRS lokal ambil & status antrean farmasi menjawab 404 jika ANTROL_WSRS_PROVIDER=vendor,
 * karena BPJS memanggil SIMRS vendor langsung. Kode route tetap ada sebagai cadangan (provider=lokal).
 */
class AntrolWsrsProvider
{
    public const PESAN = 'Service ini dilayani SIMRS vendor (ANTROL_WSRS_PROVIDER=vendor), bukan modul antrol';

    public function handle(Request $request, Closure $next): Response
    {
        if (config('antrol.wsrs.provider', 'vendor') !== 'lokal') {
            return response()->json(Hasil::gagal(self::PESAN, 404), 404);
        }

        return $next($request);
    }
}
