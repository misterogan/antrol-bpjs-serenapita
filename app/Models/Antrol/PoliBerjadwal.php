<?php

namespace App\Models\Antrol;

use Database\Factories\Antrol\PoliBerjadwalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Poli yang pernah punya jadwal menurut Referensi Jadwal Dokter BPJS (hasil sinkron). */
class PoliBerjadwal extends Model
{
    /** @use HasFactory<PoliBerjadwalFactory> */
    use HasFactory;

    protected $table = 'antrol_poli_berjadwal';

    protected $fillable = ['kodepoli', 'namapoli', 'tanggal_jadwal_terakhir'];

    protected $casts = ['tanggal_jadwal_terakhir' => 'date'];
}
