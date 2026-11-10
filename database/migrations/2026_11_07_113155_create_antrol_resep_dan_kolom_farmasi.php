<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - antrol_resep: data resep yang dikirim SIMRS vendor lewat POST /jkn/internal/resep (atau input manual farmasi).
 * - antrol_farmasi: nama & No. RM (disalin saat antrean dibuat), waktu panggil/selesai/batal,
 *   dan status pengiriman Update Waktu task ID 6 & 7 ke BPJS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('antrol_resep', function (Blueprint $table) {
            $table->id();
            $table->string('kodebooking', 30)->unique();
            $table->string('jenisresep', 20);              // Racikan | Non Racikan | Tidak ada
            $table->string('norekammedis', 30)->nullable();
            $table->string('namapasien', 100)->nullable();
            $table->string('nomorkartu', 20)->nullable();
            $table->string('kodepoli', 10)->nullable();
            $table->date('tanggal');
            $table->string('sumber', 10)->default('vendor'); // vendor | manual
            $table->timestamp('dibatalkan_at')->nullable();
            $table->timestamps();
            $table->index('tanggal');
        });

        Schema::table('antrol_farmasi', function (Blueprint $table) {
            $table->string('nama', 100)->nullable();
            $table->string('norm', 30)->nullable();
            $table->timestamp('dipanggil_at')->nullable();
            $table->timestamp('selesai_at')->nullable();
            $table->timestamp('batal_at')->nullable();
            $table->timestamp('task6_at')->nullable();
            $table->string('task6_pesan', 200)->nullable();
            $table->timestamp('task7_at')->nullable();
            $table->string('task7_pesan', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('antrol_farmasi', function (Blueprint $table) {
            $table->dropColumn(['nama', 'norm', 'dipanggil_at', 'selesai_at', 'batal_at', 'task6_at', 'task6_pesan', 'task7_at', 'task7_pesan']);
        });
        Schema::dropIfExists('antrol_resep');
    }
};
