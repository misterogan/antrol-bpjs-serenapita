<?php

namespace App\Services\Antrol;

use App\Models\Antrol\AntrolLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Validasi token WSRS mode vendor: header x-token & x-username dari BPJS diteruskan ke service
 * vendor yang hanya membaca data (default POST /jkn/sisa-antrian, kodebooking "CEKTOKEN").
 *
 * Prinsip: token hanya diloloskan jika vendor menjawab dengan jelas. Gagal terhubung, timeout,
 * HTTP 5xx, jawaban tak dikenal, atau pola config yang belum terisi selalu berarti DITOLAK.
 * Token sah di-cache sebagai hash HMAC (token mentah tidak pernah disimpan).
 */
class VendorTokenValidator
{
    public const SAH = 'sah';

    public const EXPIRED = 'expired';

    public const TIDAK_VALID = 'tidak_valid';

    public const GAGAL = 'gagal';

    /** @return self::SAH|self::EXPIRED|self::TIDAK_VALID|self::GAGAL */
    public function periksa(string $username, string $token): string
    {
        // exp JWT hanya dipakai untuk MENOLAK lebih awal; keabsahan tetap ditentukan vendor.
        $exp = $this->expJwt($token);
        if ($exp !== null && $exp <= now()->getTimestamp()) {
            return self::EXPIRED;
        }

        $kunciCache = $this->kunciCache($username, $token);
        if (Cache::get($kunciCache) === true) {
            return self::SAH;
        }

        [$hasil, $catatan] = $this->tanyaVendor($username, $token);

        // Masa cache token sah tidak melewati exp token.
        $detik = (int) config('antrol.wsrs.vendor.token_cache_menit', 2) * 60;
        if ($exp !== null) {
            $detik = min($detik, $exp - now()->getTimestamp());
        }
        if ($hasil === self::SAH && $detik > 0) {
            Cache::put($kunciCache, true, now()->addSeconds($detik));
        }

        AntrolLog::catat('vendor', 'POST '.$this->path(), $catatan['request'], $catatan['response'] + ['hasil' => $hasil], $catatan['response']['http_status']);

        return $hasil;
    }

    /**
     * Klaim exp dari token JWT vendor ("Bearer <header>.<payload>.<signature>"), dibaca TANPA
     * verifikasi tanda tangan. Null jika token bukan JWT, payload tidak terbaca, atau exp tidak ada.
     */
    public function expJwt(string $token): ?int
    {
        $jwt = preg_replace('/^Bearer\s+/i', '', trim($token));
        $bagian = explode('.', (string) $jwt);
        if (count($bagian) !== 3 || $bagian[1] === '') {
            return null;
        }

        $json = base64_decode(strtr($bagian[1], '-_', '+/'), true);
        $payload = $json === false ? null : json_decode($json, true);
        $exp = is_array($payload) ? ($payload['exp'] ?? null) : null;

        return is_int($exp) || (is_string($exp) && ctype_digit($exp)) || (is_float($exp) && is_finite($exp))
            ? (int) $exp
            : null;
    }

    /** Kunci cache dari HMAC-SHA256 username + token dengan APP_KEY. */
    public function kunciCache(string $username, string $token): string
    {
        return 'antrol.vendor_token.'.hash_hmac('sha256', $username."\n".$token, (string) config('app.key'));
    }

    /**
     * @return array{0: string, 1: array{request: array<string, mixed>, response: array<string, mixed>}}
     */
    private function tanyaVendor(string $username, string $token): array
    {
        $cfg = (array) config('antrol.wsrs.vendor', []);
        $baseUrl = rtrim((string) ($cfg['base_url'] ?? ''), '/');
        $url = $baseUrl.$this->path();
        $body = ['kodebooking' => (string) ($cfg['kodebooking_cek'] ?? 'CEKTOKEN')];

        $catatan = [
            'request' => [
                'url' => $url,
                'headers' => ['x-username' => $username, 'x-token' => $this->samarkan($token)],
                'body' => $body,
            ],
            'response' => ['http_status' => null, 'metadata' => null, 'raw' => null, 'error' => null, 'alasan' => null],
        ];

        $belumTerisi = array_filter(['expired', 'tidak_valid'], fn (string $p) => ! $this->polaTerisi($p));
        if ($baseUrl === '') {
            $catatan['response']['alasan'] = 'Alamat service validasi token SIMRS belum diisi di konfigurasi; SIMRS tidak dipanggil.';

            return [self::GAGAL, $catatan];
        }
        if ($belumTerisi) {
            $catatan['response']['alasan'] = 'Pola '.implode(' & ', $belumTerisi).' validasi token SIMRS belum diisi di konfigurasi; SIMRS tidak dipanggil.';

            return [self::GAGAL, $catatan];
        }

        try {
            $timeout = max(1, (int) ($cfg['timeout'] ?? 5));
            $res = Http::timeout($timeout)
                ->connectTimeout($timeout)
                ->acceptJson()
                ->withHeaders(['x-token' => $token, 'x-username' => $username])
                ->post($url, $body);
        } catch (Throwable $e) {
            $catatan['response']['error'] = $e->getMessage();
            $catatan['response']['alasan'] = 'SIMRS tidak dapat dihubungi atau timeout.';

            return [self::GAGAL, $catatan];
        }

        $catatan['response']['http_status'] = $res->status();
        $catatan['response']['raw'] = mb_substr($res->body(), 0, 2000);
        $json = $res->json();
        $metadata = is_array($json) && is_array($json['metadata'] ?? null) ? $json['metadata'] : null;
        $catatan['response']['metadata'] = $metadata;

        if ($res->serverError()) {
            $catatan['response']['alasan'] = 'SIMRS menjawab HTTP '.$res->status().'.';

            return [self::GAGAL, $catatan];
        }
        if (! isset($metadata['code'], $metadata['message']) || is_array($metadata['code']) || is_array($metadata['message'])) {
            $catatan['response']['alasan'] = 'Jawaban SIMRS tidak dikenali (tanpa metadata.code / metadata.message).';

            return [self::GAGAL, $catatan];
        }

        // Jika jawaban cocok dengan kedua pola (vendor menjawab sama untuk expired & tidak valid),
        // hasilnya tidak_valid; token yang benar-benar kedaluwarsa sudah ditolak lewat exp JWT.
        if ($this->cocok('tidak_valid', $metadata)) {
            return [self::TIDAK_VALID, $catatan];
        }
        if ($this->cocok('expired', $metadata)) {
            return [self::EXPIRED, $catatan];
        }
        if ($this->polaTerisi('sah') && ! $this->cocok('sah', $metadata)) {
            $catatan['response']['alasan'] = 'Jawaban SIMRS tidak cocok dengan pola token sah.';

            return [self::GAGAL, $catatan];
        }

        return [self::SAH, $catatan];
    }

    private function path(): string
    {
        return '/'.ltrim((string) config('antrol.wsrs.vendor.path_cek_token', '/jkn/sisa-antrian'), '/');
    }

    /** Pola terisi jika 'code' dan 'pesan' sama-sama tidak kosong. */
    private function polaTerisi(string $nama): bool
    {
        $pola = (array) config("antrol.wsrs.vendor.pola.{$nama}", []);

        return array_filter((array) ($pola['code'] ?? []), fn ($c) => $c !== '' && $c !== null) !== []
            && array_filter((array) ($pola['pesan'] ?? []), fn ($p) => trim((string) $p) !== '') !== [];
    }

    /** @param  array<string, mixed>  $metadata */
    private function cocok(string $nama, array $metadata): bool
    {
        if (! $this->polaTerisi($nama)) {
            return false;
        }

        $pola = (array) config("antrol.wsrs.vendor.pola.{$nama}");
        $kode = array_map(fn ($c) => (string) $c, (array) $pola['code']);
        if (! in_array((string) $metadata['code'], $kode, true)) {
            return false;
        }

        $pesan = mb_strtolower((string) $metadata['message']);
        foreach ((array) $pola['pesan'] as $p) {
            $p = mb_strtolower(trim((string) $p));
            if ($p !== '' && str_contains($pesan, $p)) {
                return true;
            }
        }

        return false;
    }

    private function samarkan(string $nilai): string
    {
        return strlen($nilai) <= 6 ? '***' : substr($nilai, 0, 4).'...'.substr($nilai, -2);
    }
}
