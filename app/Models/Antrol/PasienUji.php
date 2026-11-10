<?php

namespace App\Models\Antrol;

use Database\Factories\Antrol\PasienUjiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Data uji pasien (tahap 1). Di tahap 2 data pasien dibaca dari SIMRS. */
class PasienUji extends Model
{
    /** @use HasFactory<PasienUjiFactory> */
    use HasFactory;

    protected $table = 'antrol_pasien_uji';

    protected $fillable = ['norm', 'nomorkartu', 'nik', 'nama', 'nohp', 'tanggal_lahir'];

    protected $casts = ['tanggal_lahir' => 'date:Y-m-d'];
}
