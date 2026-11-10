<?php

namespace App\Http\Controllers\Antrol;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Halaman awal /antrol (Daftar Antrian bergaya SIMRS) dan halaman Alat Uji. Hanya navigasi. */
class DaftarAntrianController extends Controller
{
    /** Jumlah kartu per halaman Daftar Antrian (tiga berderet, seperti SIMRS). */
    private const KARTU_PER_HALAMAN = 3;

    public function index(Request $r): View
    {
        $kartu = [
            ['judul' => ['Antrian Pendaftaran', 'Onsite BPJS'], 'ikon' => 'pendaftaran', 'tujuan' => route('antrol.pendaftaran.poli-fp'), 'tombol' => 'Ambil Antrian'],
            ['judul' => ['Antrian', 'Farmasi'], 'ikon' => 'farmasi', 'tujuan' => route('antrol.farmasi'), 'tombol' => 'Ambil Antrian'],
            ['judul' => ['Monitoring', 'Antrean'], 'ikon' => 'monitoring', 'tujuan' => route('antrol.monitoring'), 'tombol' => 'Buka'],
            ['judul' => ['Log', 'Bridging'], 'ikon' => 'log', 'tujuan' => route('antrol.log'), 'tombol' => 'Buka'],
            ['judul' => ['Alat', 'Uji'], 'ikon' => 'alat', 'tujuan' => route('antrol.alat-uji'), 'tombol' => 'Buka'],
            ['judul' => ['Dokumentasi', 'API'], 'ikon' => 'dokumen', 'tujuan' => route('antrol.api-docs'), 'tombol' => 'Buka'],
        ];

        $jumlahHalaman = (int) ceil(count($kartu) / self::KARTU_PER_HALAMAN);
        $halaman = min(max(1, (int) $r->query('hal', 1)), $jumlahHalaman);

        return view('antrol.daftar-antrian', [
            'kartu' => array_slice($kartu, ($halaman - 1) * self::KARTU_PER_HALAMAN, self::KARTU_PER_HALAMAN),
            'halaman' => $halaman,
            'jumlahHalaman' => $jumlahHalaman,
        ]);
    }

    public function alatUji(): View
    {
        return view('antrol.alat-uji');
    }
}
