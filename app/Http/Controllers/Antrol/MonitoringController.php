<?php

namespace App\Http\Controllers\Antrol;

use App\Http\Controllers\Controller;
use App\Repositories\AntreanRepository;
use App\Services\Antrol\PelayananService;
use App\Services\Antrol\SinkronJadwalService;
use App\Services\Antrol\WsbpjsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Monitoring antrean belum dilayani menurut data BPJS. */
class MonitoringController extends Controller
{
    public const HARI = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu', 8 => 'Libur nasional'];

    public function __construct(private AntreanRepository $repo, private WsbpjsService $ws) {}

    public function index(): View
    {
        $monitor = session('monitor');

        return view('antrol.monitoring', [
            'jadwal' => $this->repo->semuaJadwal(),
            'sumberJadwal' => $this->repo->ringkasanSumberJadwal(),
            // Panel "Sinkron jadwal dokter (HFIS)".
            'poliBerjadwal' => $this->repo->daftarPoliBerjadwal(),
            'jedaSinkronMs' => (int) config('antrol.sinkron_jadwal.jeda_ms', 100),
            'sinkron' => session('sinkron'),
            'hari' => self::HARI,
            'monitor' => $monitor,
            'tabel' => $monitor ? $this->tabel($monitor['hasil']['response'] ?? null) : null,
        ]);
    }

    /**
     * Sinkron jadwal dokter dari HFIS untuk satu tanggal (bukan alat simulasi). Bisa memakan waktu
     * (satu panggilan per poli), jadi batas waktu eksekusi PHP dilepas khusus untuk aksi ini.
     * Opsi "ganti data fiktif" hanya berlaku jika alat simulasi aktif.
     */
    public function sinkronJadwal(Request $r, SinkronJadwalService $sinkron): RedirectResponse
    {
        $data = $r->validate([
            'tanggal' => 'required|date_format:Y-m-d',
            'ganti_fiktif' => 'nullable|boolean',
            'mode' => 'nullable|in:'.SinkronJadwalService::MODE_BERJADWAL.','.SinkronJadwalService::MODE_SEMUA,
        ], [], ['tanggal' => 'tanggal jadwal', 'mode' => 'pilihan poli']);

        $mode = $data['mode'] ?? ($this->repo->daftarPoliBerjadwal() ? SinkronJadwalService::MODE_BERJADWAL : SinkronJadwalService::MODE_SEMUA);
        $gantiFiktif = PelayananService::alatSimulasi() && $r->boolean('ganti_fiktif');

        set_time_limit(0);
        $hasil = $sinkron->sinkron($data['tanggal'], $gantiFiktif, $mode);
        $lancar = $hasil['ok'] && ! $hasil['gagal'] && ! $hasil['dihentikan'];

        return redirect()->to(route('antrol.monitoring').'#sinkron-jadwal')
            ->with('sinkron', $hasil)
            ->with($lancar ? 'pesan' : 'peringatan', $hasil['pesan']);
    }

    public function belumDilayani(): RedirectResponse
    {
        return $this->kembali('Antrean belum dilayani (semua)', $this->ws->antreanBelumDilayani());
    }

    public function belumDilayaniPerPoli(Request $r): RedirectResponse
    {
        $r->validate([
            'hari' => 'required|integer|between:1,8',
            'jadwal' => 'nullable|string|max:60',
            'kodepoli' => 'required_without:jadwal|nullable|string|max:10',
            'kodedokter' => 'required_without:jadwal|nullable|string|max:15',
            'jampraktek' => 'required_without:jadwal|nullable|string|max:20',
        ], [], ['jadwal' => 'jadwal praktek']);

        if ($r->filled('jadwal')) {
            [$kodepoli, $kodedokter, $jampraktek] = array_pad(explode('|', (string) $r->input('jadwal')), 3, '');
        } else {
            [$kodepoli, $kodedokter, $jampraktek] = [(string) $r->input('kodepoli'), (string) $r->input('kodedokter'), (string) $r->input('jampraktek')];
        }

        if ($kodepoli === '' || $kodedokter === '' || $jampraktek === '') {
            return back()->withErrors(['jadwal' => 'Pilih jadwal praktek atau isi kode poli, kode dokter, dan jam praktek.'])->withInput();
        }

        $hari = (string) $r->input('hari');
        $judul = 'Antrean belum dilayani · poli '.$kodepoli.' · dokter '.$kodedokter.' · '.(self::HARI[(int) $hari] ?? $hari).' · '.$jampraktek;

        return $this->kembali($judul, $this->ws->antreanBelumDilayaniPerPoli($kodepoli, $kodedokter, $hari, $jampraktek));
    }

    private function kembali(string $judul, array $hasil): RedirectResponse
    {
        return redirect()->route('antrol.monitoring')->withInput()->with('monitor', [
            'judul' => $judul,
            'hasil' => $hasil,
            'waktu' => now()->format('H:i:s'),
        ]);
    }

    /**
     * Ubah response BPJS menjadi tabel jika berupa daftar baris. Kolom diambil dari data apa adanya.
     *
     * @return array{kolom: list<string>, baris: list<array<string, string>>}|null
     */
    private function tabel(mixed $isi): ?array
    {
        if (is_array($isi) && isset($isi['list']) && is_array($isi['list'])) {
            $isi = $isi['list'];
        }
        if (! is_array($isi) || ! array_is_list($isi) || $isi === []) {
            return null;
        }

        $kolom = [];
        $baris = [];
        foreach ($isi as $b) {
            if (! is_array($b)) {
                return null;
            }
            $sel = [];
            foreach ($b as $k => $v) {
                $kolom[$k] = true;
                $sel[$k] = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (is_bool($v) ? ($v ? 'true' : 'false') : (string) $v);
            }
            $baris[] = $sel;
        }

        return ['kolom' => array_keys($kolom), 'baris' => $baris];
    }
}
