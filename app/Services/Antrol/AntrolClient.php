<?php

namespace App\Services\Antrol;

use App\Models\Antrol\AntrolLog;
use Illuminate\Support\Facades\Http;
use LZCompressor\LZString;
use Throwable;

/**
 * Klien WS BPJS Antrean RS.
 * Menangani header (x-cons-id, x-timestamp, x-signature, user_key),
 * dekripsi AES-256-CBC dan dekompresi LZString pada field "response".
 */
class AntrolClient
{
    public function __construct(private array $cfg) {}

    public function get(string $path): array
    {
        return $this->send('GET', $path);
    }

    public function post(string $path, array $body): array
    {
        return $this->send('POST', $path, $body);
    }

    private function send(string $method, string $path, ?array $body = null): array
    {
        $timestamp = (string) time();
        $headers = [
            'x-cons-id' => (string) $this->cfg['cons_id'],
            'x-timestamp' => $timestamp,
            'x-signature' => $this->signature($timestamp),
            'user_key' => (string) $this->cfg['user_key'],
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        $url = rtrim($this->cfg['base_url'], '/').$path;

        $hasil = [
            'method' => $method,
            'url' => $url,
            'headers' => $this->sembunyikanRahasia($headers),
            'body' => $body,
            'http_status' => null,
            'raw' => null,
            'metadata' => null,
            'response' => null,
            'error' => null,
        ];

        try {
            $req = Http::withHeaders($headers)->timeout($this->cfg['timeout'] ?? 30);
            $res = $method === 'GET'
                ? $req->get($url)
                : $req->withBody(json_encode($body), 'application/json')->post($url);

            $hasil['http_status'] = $res->status();
            $hasil['raw'] = $res->body();
            $json = $res->json();
            $hasil['metadata'] = $json['metadata'] ?? null;

            $isi = $json['response'] ?? null;
            if (is_string($isi) && $isi !== '') {
                $hasil['response'] = $this->dekrip($isi, $timestamp);
            } else {
                $hasil['response'] = $isi;
            }
        } catch (Throwable $e) {
            $hasil['error'] = $e->getMessage();
        }

        AntrolLog::catat('wsbpjs', $method.' '.$path, $body, $hasil, $hasil['http_status']);

        return $hasil;
    }

    public function signature(string $timestamp): string
    {
        $data = $this->cfg['cons_id'].'&'.$timestamp;

        return base64_encode(hash_hmac('sha256', $data, (string) $this->cfg['secret'], true));
    }

    /**
     * Dekripsi sesuai katalog: key = consid + secretkey + timestamp,
     * AES-256-CBC dengan key sha256 dan iv 16 byte pertama, lalu LZString.
     */
    public function dekrip(string $teks, string $timestamp): mixed
    {
        $key = $this->cfg['cons_id'].$this->cfg['secret'].$timestamp;
        $keyHash = hex2bin(hash('sha256', $key));
        $iv = substr($keyHash, 0, 16);

        $plain = openssl_decrypt(base64_decode($teks), 'AES-256-CBC', $keyHash, OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            return ['_gagal_dekrip' => true, 'teks' => $teks];
        }

        $json = LZString::decompressFromEncodedURIComponent($plain);
        $data = json_decode((string) $json, true);

        return $data ?? $json;
    }

    private function sembunyikanRahasia(array $headers): array
    {
        $headers['user_key'] = $this->samarkan($headers['user_key']);
        $headers['x-signature'] = $this->samarkan($headers['x-signature']);

        return $headers;
    }

    private function samarkan(string $nilai): string
    {
        return strlen($nilai) <= 6 ? '***' : substr($nilai, 0, 4).'...'.substr($nilai, -2);
    }
}
