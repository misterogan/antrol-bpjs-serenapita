<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daftar poli yang pernah punya jadwal menurut Referensi Jadwal Dokter BPJS,
 * supaya sinkron berikutnya cukup memanggil poli tersebut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('antrol_poli_berjadwal', function (Blueprint $table) {
            $table->id();
            $table->string('kodepoli', 10)->unique();
            $table->string('namapoli', 100);
            $table->date('tanggal_jadwal_terakhir');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('antrol_poli_berjadwal');
    }
};
