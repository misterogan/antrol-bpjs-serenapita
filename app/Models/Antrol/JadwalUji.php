<?php

namespace App\Models\Antrol;

use Database\Factories\Antrol\JadwalUjiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Data uji jadwal praktek dokter (tahap 1). Kode poli & dokter mengikuti HFIS. */
class JadwalUji extends Model
{
    /** @use HasFactory<JadwalUjiFactory> */
    use HasFactory;

    protected $table = 'antrol_jadwal_uji';

    public const SUMBER_FIKTIF = 'fiktif';

    public const SUMBER_BPJS = 'bpjs';

    /** Kuota non JKN untuk jadwal hasil sinkron (HFIS hanya memberi kapasitas pasien JKN). */
    public const KUOTA_NONJKN_DEFAULT = 10;

    protected $fillable = [
        'kodepoli', 'namapoli', 'kodedokter', 'namadokter', 'hari', 'jampraktek', 'kuota_jkn', 'kuota_nonjkn',
        'sumber', 'libur', 'disinkron_at',
    ];

    protected $casts = [
        'hari' => 'integer',
        'kuota_jkn' => 'integer',
        'kuota_nonjkn' => 'integer',
        'libur' => 'boolean',
        'disinkron_at' => 'datetime',
    ];
}
