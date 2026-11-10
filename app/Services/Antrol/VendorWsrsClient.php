<?php

namespace App\Services\Antrol;

use App\Models\Antrol\AntrolLog;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Klien WSRS milik SIMRS vendor: Ambil & Status Antrean Farmasi (format WSRS Antrol 2.0).
 *
 * - Token dari GET {path_auth} (header x-username, x-password), di-cache sampai exp JWT - 1 menit.
 * - Jawaban 401 (HTTP atau metadata.code): token di-refresh sekali lalu request diulang.
 * - metadata.code 200 atau 1 = sukses (config antrol.kode_sukses).
 * - Setiap request dicatat di AntrolLog arah 'vendor' tanpa password dan token.
 *
 * Setiap method mengembalikan:
 * ['ok' => bool, 'pesan' => string, 'response' => ?array, 'metadata' => ?array, 'http_status' => ?int, 'error' => ?string]
 */
class VendorWsrsClient
{
    public const KUNCI_CACHE_TOKEN = 'antrol.vendor_wsrs_token';

    /** @return array{ok: bool, pesan: string, response: ?array, metadata: ?array, http_status: ?int, error: ?string} */
    public function ambilAntreanFarmasi(string $kodebooking): array
    {
        return $this->kirim('path_ambil_farmasi', $kodebooking);
    }

    /** @return array{ok: bool, pesan: string, response: ?array, metadata: ?array, http_status: ?int, error: ?string} */
    public function statusAntreanFarmasi(string $kodebooking): array
    {
        return $this->kirim('path_status_farmasi', $kodebooking);
    }

    /** True jika metadata.code termasuk kode sukses (200 atau 1). */
    public static function kodeSukses(mixed $kode): bool
    {
        if (! is_numeric($kode)) {
            return false;
        }

        return in_array((int) $kode, array_map('intval', (array) config('antrol.kode_sukses', [200, 1])), true);
    }

    /** Lupakan token yang di-cache (dipakai saat vendor menolak token). */
    public function lupakanToken(): void
    {
        Cache::forget(self::KUNCI_CACHE_TOKEN);
    }

    /** @return array{ok: bool, pesan: string, response: ?array, metadata: ?array, http_status: ?int, error: ?string} */
    private function kirim(string $kunciPath, string $kodebooking): array
    {
        if ($this->baseUrl() === '') {
            return $this->gagal('Alamat SIMRS vendor (ANTROL_VENDOR_BASE_URL) belum diisi.');
        }

        $token = $this->token();
        if (! $token['ok']) {
            return $token['hasil'];
        }

        $hasil = $this->post($kunciPath, $kodebooking, $token['token']);
        if ($this->ditolakToken($hasil)) {
            $this->lupakanToken();
            $token = $this->token();
            if (! $token['ok']) {
                return $token['hasil'];
            }
            $hasil = $this->post($kunciPath, $kodebooking, $token['token']);
        }

        return $hasil;
    }

    /** @return array{ok: bool, pesan: string, response: ?array, metadata: ?array, http_status: ?int, error: ?string} */
    private function post(string $kunciPath, string $kodebooking, string $token): array
    {
        $path = $this->path($kunciPath);
        $body = ['kodebooking' => $kodebooking];
        $username = (string) config('antrol.wsrs.vendor.username');

        $hasil = $this->panggil(fn () => $this->http()
            ->withHeaders(['x-token' => $token, 'x-username' => $username])
            ->post($this->baseUrl().$path, $body));

        AntrolLog::catat('vendor', 'POST '.$path, ['x-username' => $username, 'body' => $body], $hasil, $hasil['http_status']);

        return $hasil;
    }

    /**
     * Token dari cache, atau minta baru ke vendor.
     *
     * @return array{ok: true, token: string}|array{ok: false, hasil: array}
     */
    private function token(): array
    {
        $tersimpan = Cache::get(self::KUNCI_CACHE_TOKEN);
        if (is_string($tersimpan) && $tersimpan !== '') {
            return ['ok' => true, 'token' => $tersimpan];
        }

        $path = $this->path('path_auth');
        $username = (string) config('antrol.wsrs.vendor.username');
        $hasil = $this->panggil(fn () => $this->http()
            ->withHeaders(['x-username' => $username, 'x-password' => (string) config('antrol.wsrs.vendor.password')])
            ->get($this->baseUrl().$path));

        $token = $hasil['response']['token'] ?? null;
        $catatan = $hasil;
        if (is_array($catatan['response'])) {
            $catatan['response']['token'] = '***';
        }
        AntrolLog::catat('vendor', 'GET '.$path, ['x-username' => $username], $catatan, $hasil['http_status']);

        if (! $hasil['ok'] || ! is_string($token) || $token === '') {
            return ['ok' => false, 'hasil' => $hasil['ok'] ? $this->gagal('Token SIMRS vendor tidak ada di jawaban auth.', $hasil) : $this->awali('Auth SIMRS vendor gagal: ', $hasil)];
        }

        $detik = $this->masaCacheToken($token);
        if ($detik > 0) {
            Cache::put(self::KUNCI_CACHE_TOKEN, $token, now()->addSeconds($detik));
        }

        return ['ok' => true, 'token' => $token];
    }

    /** Detik cache token: exp JWT - 60 detik; tanpa exp memakai config token_cache_menit_tanpa_exp. */
    private function masaCacheToken(string $token): int
    {
        $exp = app(VendorTokenValidator::class)->expJwt($token);
        if ($exp === null) {
            return (int) config('antrol.wsrs.vendor.token_cache_menit_tanpa_exp', 5) * 60;
        }

        return $exp - 60 - now()->getTimestamp();
    }

    /**
     * @param  callable(): Response  $kirim
     * @return array{ok: bool, pesan: string, response: ?array, metadata: ?array, http_status: ?int, error: ?string}
     */
    private function panggil(callable $kirim): array
    {
        try {
            $res = $kirim();
        } catch (Throwable $e) {
            return $this->gagal('SIMRS vendor tidak dapat dihubungi atau timeout ('.$this->timeout().' detik).', ['error' => $e->getMessage()]);
        }

        $json = $res->json();
        $metadata = is_array($json) && is_array($json['metadata'] ?? null) ? $json['metadata'] : null;
        $response = is_array($json) && is_array($json['response'] ?? null) ? $json['response'] : null;
        $ok = $res->successful() && self::kodeSukses($metadata['code'] ?? null);

        $pesan = $metadata['message'] ?? null;
        if (! is_string($pesan) || $pesan === '') {
            $pesan = $ok ? 'Ok' : 'SIMRS vendor menjawab HTTP '.$res->status().' tanpa metadata.message.';
        }

        return [
            'ok' => $ok,
            'pesan' => $pesan,
            'response' => $response,
            'metadata' => $metadata,
            'http_status' => $res->status(),
            'error' => null,
        ];
    }

    /** @param  array<string, mixed>  $hasil */
    private function ditolakToken(array $hasil): bool
    {
        return $hasil['http_status'] === 401 || (string) ($hasil['metadata']['code'] ?? '') === '401';
    }

    private function http(): PendingRequest
    {
        return Http::timeout($this->timeout())->connectTimeout($this->timeout())->acceptJson();
    }

    private function timeout(): int
    {
        return max(1, (int) config('antrol.wsrs.vendor.wsrs_timeout', 10));
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('antrol.wsrs.vendor.base_url'), '/');
    }

    private function path(string $kunci): string
    {
        return '/'.ltrim((string) config("antrol.wsrs.vendor.{$kunci}"), '/');
    }

    /**
     * @param  array<string, mixed>  $tambahan
     * @return array{ok: bool, pesan: string, response: ?array, metadata: ?array, http_status: ?int, error: ?string}
     */
    private function gagal(string $pesan, array $tambahan = []): array
    {
        return array_merge(['response' => null, 'metadata' => null, 'http_status' => null, 'error' => null], $tambahan, ['ok' => false, 'pesan' => $pesan]);
    }

    /** @param  array<string, mixed>  $hasil */
    private function awali(string $awalan, array $hasil): array
    {
        $hasil['pesan'] = $awalan.$hasil['pesan'];

        return $hasil;
    }
}
