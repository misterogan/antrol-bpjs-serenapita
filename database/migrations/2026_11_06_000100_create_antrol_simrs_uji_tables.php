<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data uji alur SIMRS rawat jalan (tahap 1):
 * pasien, jadwal dokter, dan status antrean poli yang berurutan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('antrol_pasien_uji', function (Blueprint $t) {
            $t->id();
            $t->string('norm', 20)->unique();
            $t->string('nomorkartu', 20)->nullable()->index();
            $t->string('nik', 20)->nullable()->index();
            $t->string('nama', 100);
            $t->string('nohp', 20)->nullable();
            $t->date('tanggal_lahir')->nullable();
            $t->timestamps();
        });

        Schema::create('antrol_jadwal_uji', function (Blueprint $t) {
            $t->id();
            $t->string('kodepoli', 10);
            $t->string('namapoli', 100);
            $t->string('kodedokter', 15);
            $t->string('namadokter', 100);
            $t->unsignedTinyInteger('hari');            // 1 = Senin s.d. 7 = Minggu
            $t->string('jampraktek', 20);               // 08:00-12:00
            $t->unsignedSmallInteger('kuota_jkn')->default(30);
            $t->unsignedSmallInteger('kuota_nonjkn')->default(10);
            $t->timestamps();
            $t->index(['hari', 'kodepoli']);
        });

        Schema::table('antrol_poli_uji', function (Blueprint $t) {
            $t->string('status', 15)->default('terdaftar'); // terdaftar | checkin | dipanggil | dilayani | selesai | batal
            $t->string('norm', 20)->nullable();
            $t->string('nik', 20)->nullable();
            $t->string('nama', 100)->nullable();
            $t->string('nohp', 20)->nullable();
            $t->string('jenispasien', 10)->default('JKN');
            $t->string('namapoli', 100)->nullable();
            $t->string('kodedokter', 15)->nullable();
            $t->string('namadokter', 100)->nullable();
            $t->string('jampraktek', 20)->nullable();
            $t->unsignedTinyInteger('jeniskunjungan')->nullable();
            $t->string('nomorreferensi', 30)->nullable();
            $t->string('nomorantrean', 20)->nullable();
            $t->unsignedInteger('angkaantrean')->nullable();
            $t->boolean('wajib_fp')->default(false);
            $t->timestamp('taskid3_at')->nullable();
            $t->timestamp('dipanggil_at')->nullable();
            $t->timestamp('taskid4_at')->nullable();
            $t->timestamp('dikirim_bpjs_at')->nullable();
            $t->index(['tanggal', 'kodepoli']);
        });
    }

    public function down(): void
    {
        Schema::table('antrol_poli_uji', function (Blueprint $t) {
            $t->dropIndex(['tanggal', 'kodepoli']);
            $t->dropColumn([
                'status', 'norm', 'nik', 'nama', 'nohp', 'jenispasien', 'namapoli', 'kodedokter',
                'namadokter', 'jampraktek', 'jeniskunjungan', 'nomorreferensi', 'nomorantrean',
                'angkaantrean', 'wajib_fp', 'taskid3_at', 'dipanggil_at', 'taskid4_at', 'dikirim_bpjs_at',
            ]);
        });
        Schema::dropIfExists('antrol_jadwal_uji');
        Schema::dropIfExists('antrol_pasien_uji');
    }
};
