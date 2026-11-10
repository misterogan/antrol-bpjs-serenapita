<?php

namespace App\Http\Middleware;

use App\Models\Antrol\AntrolLog;
use App\Services\Antrol\Hasil;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pengaman API internal untuk SIMRS vendor (/jkn/internal/*):
 * header x-api-key (hash_equals dengan ANTROL_INTERNAL_API_KEY; kosong = semua ditolak)
 * dan ANTROL_INTERNAL_IP_ALLOWLIST. Setiap request dicatat di log arah "internal" tanpa kunci API.
 */
class AntrolInternalApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $kunci = (string) config('antrol.internal.api_key');
        $diizinkan = array_values(array_filter(array_map('trim', explode(',', (string) config('antrol.internal.ip_allowlist')))));

        $tolak = match (true) {
            $kunci === '' => [Hasil::gagal('API internal belum dikonfigurasi (ANTROL_INTERNAL_API_KEY kosong)', 401), 401],
            ! hash_equals($kunci, (string) $request->header('x-api-key')) => [Hasil::gagal('Kunci API tidak valid', 401), 401],
            $diizinkan !== [] && ! in_array((string) $request->ip(), $diizinkan, true) => [Hasil::gagal('Alamat IP tidak diizinkan', 403), 403],
            default => null,
        };

        $respons = $tolak ? response()->json($tolak[0], $tolak[1]) : $next($request);
        $this->catat($request, $respons);

        return $respons;
    }

    private function catat(Request $request, Response $respons): void
    {
        $isi = json_decode((string) $respons->getContent(), true);

        AntrolLog::catat(
            'internal',
            $request->method().' '.$request->path(),
            ['ip' => $request->ip(), 'query' => $request->query(), 'body' => $request->isMethod('GET') ? null : $request->json()->all()],
            is_array($isi) ? $isi : ['raw' => mb_substr((string) $respons->getContent(), 0, 2000)],
            $respons->getStatusCode()
        );
    }
}
