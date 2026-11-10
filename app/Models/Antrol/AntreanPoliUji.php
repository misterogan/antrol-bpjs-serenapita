<?php

namespace App\Models\Antrol;

use Database\Factories\Antrol\AntreanPoliUjiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Data uji antrean poli (tahap 1, sebelum tersambung ke SIMRS). */
class AntreanPoliUji extends Model
{
    /** @use HasFactory<AntreanPoliUjiFactory> */
    use HasFactory;

    protected $table = 'antrol_poli_uji';

    protected $fillable = [
        'kodebooking', 'tanggal', 'nomorkartu', 'kodepoli', 'checkin', 'batal', 'taskid5_at', 'jenisresep',
        'status', 'norm', 'nik', 'nama', 'nohp', 'jenispasien', 'namapoli', 'kodedokter', 'namadokter',
        'jampraktek', 'jeniskunjungan', 'nomorreferensi', 'nomorantrean', 'angkaantrean', 'wajib_fp',
        'taskid3_at', 'dipanggil_at', 'taskid4_at', 'dikirim_bpjs_at',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'checkin' => 'boolean',
        'batal' => 'boolean',
        'wajib_fp' => 'boolean',
        'angkaantrean' => 'integer',
        'jeniskunjungan' => 'integer',
        'taskid3_at' => 'datetime',
        'dipanggil_at' => 'datetime',
        'taskid4_at' => 'datetime',
        'taskid5_at' => 'datetime',
        'dikirim_bpjs_at' => 'datetime',
    ];

    /**
     * Status yang berlaku, juga untuk baris lama dari halaman Data uji
     * yang hanya mengisi penanda checkin / taskid5_at / batal.
     */
    public function statusEfektif(): string
    {
        return match (true) {
            $this->batal => 'batal',
            $this->taskid5_at !== null => 'selesai',
            $this->status === 'terdaftar' && $this->checkin => 'checkin',
            default => (string) ($this->status ?: 'terdaftar'),
        };
    }
}
