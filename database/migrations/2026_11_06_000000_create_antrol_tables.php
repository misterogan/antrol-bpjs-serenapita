<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('antrol_poli_uji', function (Blueprint $t) {
            $t->id();
            $t->string('kodebooking', 30)->unique();
            $t->date('tanggal');
            $t->string('nomorkartu', 20)->nullable();
            $t->string('kodepoli', 10)->nullable();
            $t->boolean('checkin')->default(false);
            $t->boolean('batal')->default(false);
            $t->timestamp('taskid5_at')->nullable();
            $t->string('jenisresep', 20)->nullable();
            $t->timestamps();
        });

        Schema::create('antrol_farmasi', function (Blueprint $t) {
            $t->id();
            $t->string('kodebooking', 30);
            $t->date('tanggal');
            $t->unsignedInteger('nomorantrean');
            $t->string('jenisresep', 20);
            $t->string('status', 15)->default('menunggu'); // menunggu | dipanggil | selesai
            $t->string('keterangan', 200)->nullable();
            $t->timestamp('dikirim_bpjs_at')->nullable();
            $t->timestamps();
            $t->unique(['kodebooking', 'tanggal']);   // 1 kode booking 1 antrean per hari
            $t->unique(['tanggal', 'nomorantrean']);
        });

        Schema::create('antrol_wsrs_tokens', function (Blueprint $t) {
            $t->id();
            $t->string('username', 100);
            $t->string('token', 100)->unique();
            $t->timestamp('expires_at');
            $t->timestamps();
        });

        Schema::create('antrol_logs', function (Blueprint $t) {
            $t->id();
            $t->string('arah', 10);          // wsbpjs | wsrs
            $t->string('endpoint', 200);
            $t->json('request')->nullable();
            $t->json('response')->nullable();
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('antrol_logs');
        Schema::dropIfExists('antrol_wsrs_tokens');
        Schema::dropIfExists('antrol_farmasi');
        Schema::dropIfExists('antrol_poli_uji');
    }
};
