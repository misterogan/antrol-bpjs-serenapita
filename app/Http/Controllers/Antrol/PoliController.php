<?php

namespace App\Http\Controllers\Antrol;

use App\Http\Controllers\Controller;
use App\Repositories\AntreanRepository;
use App\Services\Antrol\PelayananService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Antrean poli hari ini: check in, panggil, mulai, selesai (dengan resep), batal. */
class PoliController extends Controller
{
    public function __construct(private AntreanRepository $repo, private PelayananService $layanan) {}

    public function index(Request $r): View
    {
        $tanggal = now()->toDateString();
        $kodepoli = $r->query('kodepoli') ?: null;
        $kodedokter = $r->query('kodedokter') ?: null;

        $semuaHariIni = $this->repo->daftarAntreanPoli($tanggal);
        $antrean = array_values(array_filter($semuaHariIni, fn (array $a) => (! $kodepoli || $a['kodepoli'] === $kodepoli)
            && (! $kodedokter || $a['kodedokter'] === $kodedokter)));

        // Pilihan poli & dokter: dari jadwal hari ini ditambah yang muncul di antrean.
        $pilihan = collect($this->repo->daftarJadwal($tanggal))
            ->map(fn (array $j) => ['kodepoli' => $j['kodepoli'], 'namapoli' => $j['namapoli'], 'kodedokter' => $j['kodedokter'], 'namadokter' => $j['namadokter']])
            ->concat(collect($semuaHariIni)->filter(fn (array $a) => $a['kodepoli'])->map(fn (array $a) => [
                'kodepoli' => $a['kodepoli'], 'namapoli' => $a['namapoli'] ?? $a['kodepoli'],
                'kodedokter' => $a['kodedokter'], 'namadokter' => $a['namadokter'] ?? $a['kodedokter'],
            ]));

        $jumlah = array_count_values(array_column($antrean, 'status'));

        return view('antrol.poli', [
            'antrean' => $antrean,
            'kodepoli' => $kodepoli,
            'kodedokter' => $kodedokter,
            'poliList' => $pilihan->unique('kodepoli')->sortBy('namapoli')->values(),
            'dokterList' => $pilihan->filter(fn ($p) => $p['kodedokter'])->unique(fn ($p) => $p['kodepoli'].'|'.$p['kodedokter'])->sortBy('namadokter')->values(),
            'jumlah' => $jumlah,
            'jenisResep' => PelayananService::JENIS_RESEP,
        ]);
    }

    public function aksi(Request $r, string $kodebooking, string $aksi): RedirectResponse
    {
        $hasil = $this->layanan->aksiPoli($kodebooking, $aksi, $r->input('jenisresep'));

        return $this->kembali($hasil);
    }

    /** Kirim ulang Tambah Antrean ke BPJS (mode simulasi). */
    public function kirimBpjs(string $kodebooking): RedirectResponse
    {
        return $this->kembali($this->layanan->kirimUlangTambahAntrean($kodebooking));
    }

    private function kembali(array $hasil): RedirectResponse
    {
        $balik = back();
        if (! $hasil['ok']) {
            $balik->withErrors(['poli' => $hasil['pesan']]);
        } else {
            $balik->with('pesan', $hasil['pesan']);
        }

        return $balik->with(['peringatan' => $hasil['peringatan'], 'bpjs' => $hasil['bpjs'] ?: null]);
    }
}
