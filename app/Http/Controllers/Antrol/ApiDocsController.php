<?php

namespace App\Http\Controllers\Antrol;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Dokumentasi API (Swagger UI) dari docs/openapi.yaml. */
class ApiDocsController extends Controller
{
    /** Versi swagger-ui-dist yang dipatok jika aset lokal public/vendor/swagger-ui belum ada. */
    public const VERSI_SWAGGER_UI = '5.17.14';

    public function index(): View
    {
        $lokal = is_file(public_path('vendor/swagger-ui/swagger-ui-bundle.js'));
        $cdn = 'https://cdn.jsdelivr.net/npm/swagger-ui-dist@'.self::VERSI_SWAGGER_UI;

        return view('antrol.api-docs', [
            'css' => $lokal ? asset('vendor/swagger-ui/swagger-ui.css') : $cdn.'/swagger-ui.css',
            'js' => $lokal ? asset('vendor/swagger-ui/swagger-ui-bundle.js') : $cdn.'/swagger-ui-bundle.js',
        ]);
    }

    public function spesifikasi(): BinaryFileResponse
    {
        return response()->file(base_path('docs/openapi.yaml'), ['Content-Type' => 'application/yaml; charset=utf-8']);
    }
}
