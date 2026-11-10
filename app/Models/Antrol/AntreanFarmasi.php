<?php

namespace App\Models\Antrol;

use Database\Factories\Antrol\AntreanFarmasiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AntreanFarmasi extends Model
{
    /** @use HasFactory<AntreanFarmasiFactory> */
    use HasFactory;

    protected $table = 'antrol_farmasi';

    /** Diambil di RS (halaman Farmasi / Uji service): harus dikirim lewat Tambah Antrean Farmasi. */
    public const SUMBER_ONSITE = 'onsite';

    /** Diambil lewat WSRS ambilantreanfarmasi (Mobile JKN): sudah diketahui BPJS, tidak dikirim lagi. */
    public const SUMBER_WSRS = 'wsrs';

    public const MENUNGGU = 'menunggu';

    public const DIPANGGIL = 'dipanggil';

    public const SELESAI = 'selesai';

    /** Dibatalkan petugas farmasi atau lewat pembatalan resep vendor. Tidak dihitung di total & sisa WSRS. */
    public const BATAL = 'batal';

    protected $fillable = [
        'kodebooking', 'tanggal', 'nomorantrean', 'jenisresep', 'status', 'keterangan', 'dikirim_bpjs_at', 'sumber',
        'nama', 'norm', 'dipanggil_at', 'selesai_at', 'batal_at', 'task6_at', 'task6_pesan', 'task7_at', 'task7_pesan',
    ];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'nomorantrean' => 'integer',
        'dikirim_bpjs_at' => 'datetime',
        'dipanggil_at' => 'datetime',
        'selesai_at' => 'datetime',
        'batal_at' => 'datetime',
        'task6_at' => 'datetime',
        'task7_at' => 'datetime',
    ];
}
