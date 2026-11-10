<?php

namespace App\Http\Middleware;

use App\Services\Antrol\PelayananService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route alat simulasi (pendaftaran simulasi, poli simulasi, data uji) menjawab 404 jika ANTROL_TAMPILKAN_ALAT_SIMULASI=false. */
class AntrolAlatSimulasi
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(PelayananService::alatSimulasi(), 404);

        return $next($request);
    }
}
