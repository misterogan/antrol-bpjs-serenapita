<?php

namespace App\Http\Controllers\Antrol;

use App\Http\Controllers\Controller;
use App\Models\Antrol\AntreanFarmasi;
use App\Models\Antrol\AntreanPoliUji;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Kelola data uji lokal (tahap 1) & panggil antrean farmasi. */
class DataUjiController extends Controller
{
    public function index()
    {
        $hari = now()->toDateString();

        return view('antrol.data-uji', [
            'poli' => AntreanPoliUji::orderByDesc('id')->limit(50)->get(),
            'farmasi' => AntreanFarmasi::whereDate('tanggal', $hari)->orderBy('nomorantrean')->get(),
        ]);
    }

    public function simpanPoli(Request $r)
    {
        $d = $r->validate([
            'kodebooking' => 'nullable|string|max:30|unique:antrol_poli_uji,kodebooking',
            'tanggal' => 'required|date',
            'nomorkartu' => 'nullable|string|max:20',
            'kodepoli' => 'nullable|string|max:10',
            'jenisresep' => 'nullable|in:Racikan,Non Racikan,Tidak ada',
            'checkin' => 'nullable|boolean',
            'taskid5' => 'nullable|boolean',
        ]);

        AntreanPoliUji::create([
            'kodebooking' => $d['kodebooking'] ?: now()->format('Ymd').strtoupper(Str::random(4)),
            'tanggal' => $d['tanggal'],
            'nomorkartu' => $d['nomorkartu'] ?? null,
            'kodepoli' => $d['kodepoli'] ?? null,
            'jenisresep' => $d['jenisresep'] ?? null,
            'checkin' => (bool) ($d['checkin'] ?? false),
            'taskid5_at' => ! empty($d['taskid5']) ? now() : null,
        ]);

        return back()->with('pesan', 'Data uji antrean poli tersimpan.');
    }

    public function aksiPoli(Request $r, AntreanPoliUji $poli)
    {
        match ($r->input('aksi')) {
            'checkin' => $poli->update(['checkin' => true]),
            'taskid5' => $poli->update(['taskid5_at' => now()]),
            'batal' => $poli->update(['batal' => true]),
            default => null,
        };

        return back();
    }

    public function aksiFarmasi(Request $r, AntreanFarmasi $farmasi)
    {
        $status = match ($r->input('aksi')) {
            'panggil' => 'dipanggil',
            'selesai' => 'selesai',
            default => $farmasi->status,
        };
        $farmasi->update(['status' => $status]);

        return back();
    }
}
