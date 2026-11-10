<?php

namespace App\Models\Antrol;

use Database\Factories\Antrol\ResepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Data resep per kode booking, dikirim SIMRS vendor (POST /jkn/internal/resep) atau diinput manual oleh farmasi. */
class Resep extends Model
{
    /** @use HasFactory<ResepFactory> */
    use HasFactory;

    public const SUMBER_VENDOR = 'vendor';

    public const SUMBER_MANUAL = 'manual';

    public const JENIS = ['Racikan', 'Non Racikan', 'Tidak ada'];

    protected $table = 'antrol_resep';

    protected $fillable = ['kodebooking', 'jenisresep', 'norekammedis', 'namapasien', 'nomorkartu', 'kodepoli', 'tanggal', 'sumber', 'dibatalkan_at'];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'dibatalkan_at' => 'datetime',
    ];
}
