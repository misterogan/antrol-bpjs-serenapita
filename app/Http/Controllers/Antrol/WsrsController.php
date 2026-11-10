<?php

namespace App\Http\Controllers\Antrol;

use App\Http\Controllers\Controller;
use App\Models\Antrol\AntreanFarmasi;
use App\Models\Antrol\AntrolLog;
use App\Models\Antrol\WsrsToken;
use App\Services\Antrol\AntreanFarmasiService;
use App\Services\Antrol\Hasil;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * WSRS: service milik RS yang dipanggil BPJS / Mobile JKN. URL default (lihat config antrol.wsrs):
 * - GET  /jkn/auth                     (header x-username, x-password)
 * - POST /jkn/ambil-antrean-farmasi    (header x-token, x-username)  {"kodebooking": "..."}
 * - POST /jkn/status-antrean-farmasi   (header x-token, x-username)  {"kodebooking": "..."}
 */
class WsrsController extends Controller
{
    public function token(Request $request)
    {
        // Mode vendor: token diterbitkan Get Token SIMRS vendor, jadi service token modul ini dimatikan.
        abort_if(config('antrol.wsrs.token_mode') === 'vendor', 404);

        $user = (string) $request->header('x-username');
        $pass = (string) $request->header('x-password');

        $valid = $user !== ''
            && hash_equals((string) config('antrol.wsrs.username'), $user)
            && hash_equals((string) config('antrol.wsrs.password'), $pass);

        if (! $valid) {
            $hasil = Hasil::gagal('Username atau Password Tidak Sesuai');
        } else {
            $token = Str::random(64);
            WsrsToken::create([
                'username' => $user,
                'token' => $token,
                'expires_at' => now()->addMinutes((int) config('antrol.wsrs.token_ttl_minutes', 60)),
            ]);
            WsrsToken::where('expires_at', '<', now()->subDay())->delete();
            $hasil = Hasil::ok(['token' => $token]);
        }

        AntrolLog::catat('wsrs', 'GET auth', ['x-username' => $user], $hasil, Hasil::kode($hasil));

        return response()->json($hasil, Hasil::kode($hasil));
    }

    public function ambilAntreanFarmasi(Request $request, AntreanFarmasiService $svc)
    {
        $hasil = $svc->ambil($request->input('kodebooking'), '', AntreanFarmasi::SUMBER_WSRS);

        return $this->balas($request, 'POST ambilantreanfarmasi', $hasil);
    }

    public function statusAntreanFarmasi(Request $request, AntreanFarmasiService $svc)
    {
        $hasil = $svc->status($request->input('kodebooking'));

        return $this->balas($request, 'POST statusantreanfarmasi', $hasil);
    }

    private function balas(Request $request, string $endpoint, array $hasil)
    {
        AntrolLog::catat('wsrs', $endpoint, $request->all(), $hasil, Hasil::kode($hasil));

        return response()->json($hasil, Hasil::kode($hasil));
    }
}
