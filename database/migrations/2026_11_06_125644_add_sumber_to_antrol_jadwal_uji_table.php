<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda asal jadwal: 'fiktif' (seeder/manual) atau 'bpjs' (hasil sinkron Referensi Jadwal Dokter).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('antrol_jadwal_uji', function (Blueprint $table) {
            $table->string('sumber', 10)->default('fiktif');
            $table->boolean('libur')->default(false);
            $table->timestamp('disinkron_at')->nullable();
            $table->index(['sumber', 'hari', 'kodepoli']);
        });
    }

    public function down(): void
    {
        Schema::table('antrol_jadwal_uji', function (Blueprint $table) {
            $table->dropIndex(['sumber', 'hari', 'kodepoli']);
            $table->dropColumn(['sumber', 'libur', 'disinkron_at']);
        });
    }
};
