<?php

namespace App\Providers;

use App\Http\Middleware\AntrolBahasa;
use App\Http\Middleware\AntrolInternalApi;
use App\Http\Middleware\WsrsToken;
use App\Repositories\AntreanRepository;
use App\Repositories\BpjsAntreanRepository;
use App\Repositories\LocalAntreanRepository;
use App\Services\Antrol\AntrolClient;
use App\Services\Antrol\Hasil;
use App\Services\Antrol\WsbpjsService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AntrolServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 'bpjs': antrean farmasi diperiksa ke BPJS + data resep dari SIMRS vendor; selain itu tabel lokal (uji).
        $this->app->bind(AntreanRepository::class, function ($app) {
            return config('antrol.sumber_data') === 'bpjs'
                ? $app->make(BpjsAntreanRepository::class)
                : new LocalAntreanRepository;
        });

        $this->app->singleton(AntrolClient::class, fn () => new AntrolClient(config('antrol.bpjs')));

        $this->app->singleton(WsbpjsService::class, fn ($app) => new WsbpjsService(
            $app->make(AntrolClient::class),
            config('antrol.paths'),
            config('antrol.body', [])
        ));
    }

    /**
     * Awalan URL WSRS dari config antrol.wsrs.prefix (default "jkn").
     * Ditolak jika kosong atau sama dengan awalan halaman web "antrol", supaya route tidak bertabrakan.
     */
    public static function prefixWsrs(): string
    {
        $prefix = trim((string) config('antrol.wsrs.prefix', 'jkn'), '/');
        $awalPertama = strtolower(explode('/', $prefix)[0]);
        if ($prefix === '' || $awalPertama === 'antrol') {
            throw new InvalidArgumentException('ANTROL_WSRS_PREFIX tidak boleh kosong atau diawali "antrol" (dipakai halaman web modul antrol).');
        }

        return $prefix;
    }

    /** Mode token WSRS dari config antrol.wsrs.token_mode: 'lokal' atau 'vendor'. */
    public static function modeTokenWsrs(): string
    {
        $mode = (string) config('antrol.wsrs.token_mode', 'lokal');
        if (! in_array($mode, ['lokal', 'vendor'], true)) {
            throw new InvalidArgumentException("ANTROL_WSRS_TOKEN_MODE harus 'lokal' atau 'vendor', bukan '{$mode}'.");
        }

        return $mode;
    }

    public function boot(): void
    {
        self::modeTokenWsrs();

        $this->app['router']->aliasMiddleware('wsrs.token', WsrsToken::class);
        $this->app['router']->aliasMiddleware('antrol.internal', AntrolInternalApi::class);

        // Rate limit API internal SIMRS vendor per IP, jawaban dengan format metadata yang sama.
        RateLimiter::for('antrol-internal', fn (Request $request) => Limit::perMinute(max(1, (int) config('antrol.internal.per_menit', 120)))
            ->by('antrol-internal|'.$request->ip())
            ->response(fn () => response()->json(Hasil::gagal('Terlalu banyak permintaan, coba lagi sebentar', 429), 429)));

        Route::prefix(self::prefixWsrs())
            ->middleware('api')
            ->group(base_path('routes/antrol_api.php'));

        Route::middleware(['web', AntrolBahasa::class])
            ->group(base_path('routes/antrol_web.php'));
    }
}
