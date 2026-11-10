<?php

namespace App\Models\Antrol;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/** Log setiap request WSBPJS & WSRS, berguna sebagai bukti saat UAT. */
class AntrolLog extends Model
{
    protected $table = 'antrol_logs';

    protected $fillable = ['arah', 'endpoint', 'request', 'response', 'http_status'];

    protected $casts = ['request' => 'array', 'response' => 'array'];

    public static function catat(string $arah, string $endpoint, $request, $response, ?int $status): void
    {
        try {
            static::create([
                'arah' => $arah,
                'endpoint' => $endpoint,
                'request' => $request,
                'response' => $response,
                'http_status' => $status,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
