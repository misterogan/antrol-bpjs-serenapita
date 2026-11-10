<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sumber antrean farmasi: 'onsite' (halaman Farmasi / Uji service) atau 'wsrs'
 * (diambil lewat WSRS ambilantreanfarmasi dari Mobile JKN, sudah diketahui BPJS).
 * Antrean lama ditandai 'wsrs' berdasarkan log WSRS ambilantreanfarmasi yang berhasil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('antrol_farmasi', function (Blueprint $table) {
            $table->string('sumber', 10)->default('onsite');
        });

        DB::table('antrol_logs')
            ->where('arah', 'wsrs')
            ->where('endpoint', 'POST ambilantreanfarmasi')
            ->orderBy('id')
            ->each(function (object $log) {
                $request = json_decode((string) $log->request, true);
                $response = json_decode((string) $log->response, true);
                $kodebooking = $request['kodebooking'] ?? null;
                $nomor = $response['response']['nomorantrean'] ?? null;
                if ((int) ($response['metadata']['code'] ?? 0) !== 200 || ! is_string($kodebooking) || $nomor === null) {
                    return;
                }

                DB::table('antrol_farmasi')
                    ->where('kodebooking', trim($kodebooking))
                    ->where('nomorantrean', (int) $nomor)
                    ->whereDate('tanggal', substr((string) $log->created_at, 0, 10))
                    ->update(['sumber' => 'wsrs']);
            });
    }

    public function down(): void
    {
        Schema::table('antrol_farmasi', function (Blueprint $table) {
            $table->dropColumn('sumber');
        });
    }
};
