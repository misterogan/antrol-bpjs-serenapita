<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pakai Bahasa Indonesia (pesan validasi, nama hari/bulan) di halaman modul antrol. */
class AntrolBahasa
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale('id');

        return $next($request);
    }
}
