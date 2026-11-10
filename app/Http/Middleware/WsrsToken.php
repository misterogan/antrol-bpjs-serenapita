<?php

namespace App\Http\Middleware;

use App\Models\Antrol\AntrolLog;
use App\Models\Antrol\WsrsToken as Token;
use App\Services\Antrol\Hasil;
use App\Services\Antrol\VendorTokenValidator;
use Closure;
use Illuminate\Http\Request;

/**
 * Validasi header x-token & x-username pada setiap request WSRS dari BPJS.
 * Mode lokal (default): token dari /jkn/auth modul ini. Mode vendor: diperiksa ke service vendor
 * lewat VendorTokenValidator. Respons "Token Expired" mengikuti saran form UAT.
 */
class WsrsToken
{
    public function __construct(private VendorTokenValidator $validator) {}

    public function handle(Request $request, Closure $next)
    {
        $username = (string) $request->header('x-username');
        $token = (string) $request->header('x-token');

        $gagal = null;
        if ($username === '' || $token === '') {
            $gagal = Hasil::gagal('Header x-token dan x-username wajib diisi');
        } elseif (config('antrol.wsrs.token_mode') === 'vendor') {
            $gagal = match ($this->validator->periksa($username, $token)) {
                VendorTokenValidator::SAH => null,
                VendorTokenValidator::EXPIRED => Hasil::gagal('Token Expired'),
                VendorTokenValidator::TIDAK_VALID => Hasil::gagal('Token Tidak Valid'),
                default => Hasil::gagal('Token tidak dapat divalidasi'),
            };
        } elseif ($username !== (string) config('antrol.wsrs.username')) {
            $gagal = Hasil::gagal('Username Tidak Sesuai');
        } else {
            $row = Token::where('token', $token)->where('username', $username)->first();
            if (! $row) {
                $gagal = Hasil::gagal('Token Tidak Valid');
            } elseif ($row->expires_at->isPast()) {
                $gagal = Hasil::gagal('Token Expired');
            }
        }

        if ($gagal) {
            AntrolLog::catat('wsrs', $request->method().' '.$request->path(), $request->all(), $gagal, 201);

            return response()->json($gagal, 201);
        }

        return $next($request);
    }
}
