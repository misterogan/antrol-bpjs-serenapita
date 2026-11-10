<?php

namespace App\Repositories;

use App\Models\Antrol\AntreanFarmasi;
use App\Models\Antrol\AntreanPoliUji;
use App\Models\Antrol\JadwalUji;
use App\Models\Antrol\PasienUji;
use App\Models\Antrol\PoliBerjadwal;
use App\Models\Antrol\Resep;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class LocalAntreanRepository implements AntreanRepository
{
    /* ---------------------------- Kontrak WSRS ---------------------------- */

    public function cariAntreanPoli(string $kodebooking): ?array
    {
        $poli = AntreanPoliUji::where('kodebooking', $kodebooking)->first();
        if (! $poli) {
            return null;
        }

        return [
            'kodebooking' => $poli->kodebooking,
            'tanggal' => $poli->tanggal->format('Y-m-d'),
            'batal' => (bool) $poli->batal,
            'checkin' => (bool) $poli->checkin,
            'taskid5' => $poli->taskid5_at !== null,
            'jenisresep' => $poli->jenisresep,
            'nama' => $poli->nama,
            'norm' => $poli->norm,
        ];
    }

    public function cariAntreanFarmasi(string $kodebooking, string $tanggal): ?array
    {
        $f = AntreanFarmasi::where('kodebooking', $kodebooking)->whereDate('tanggal', $tanggal)->first();

        return $f ? $f->toArray() : null;
    }

    public function buatAntreanFarmasi(string $kodebooking, string $tanggal, string $jenisresep, string $keterangan = '', string $sumber = AntreanFarmasi::SUMBER_ONSITE, array $pasien = []): array
    {
        return DB::transaction(function () use ($kodebooking, $tanggal, $jenisresep, $keterangan, $sumber, $pasien) {
            $nomor = (int) AntreanFarmasi::whereDate('tanggal', $tanggal)->lockForUpdate()->max('nomorantrean') + 1;

            return AntreanFarmasi::create([
                'kodebooking' => $kodebooking,
                'tanggal' => $tanggal,
                'nomorantrean' => $nomor,
                'jenisresep' => $jenisresep,
                'status' => 'menunggu',
                'keterangan' => $keterangan,
                'sumber' => $sumber,
                'nama' => $pasien['nama'] ?? null,
                'norm' => $pasien['norm'] ?? null,
            ])->toArray();
        });
    }

    public function simpanAntreanFarmasiBernomor(string $kodebooking, string $tanggal, int $nomorantrean, string $jenisresep, string $keterangan, string $sumber, array $pasien = []): array
    {
        return AntreanFarmasi::create([
            'kodebooking' => $kodebooking,
            'tanggal' => $tanggal,
            'nomorantrean' => $nomorantrean,
            'jenisresep' => $jenisresep,
            'status' => AntreanFarmasi::MENUNGGU,
            'keterangan' => $keterangan,
            'sumber' => $sumber,
            'nama' => $pasien['nama'] ?? null,
            'norm' => $pasien['norm'] ?? null,
        ])->toArray();
    }

    public function statistikFarmasi(string $tanggal): array
    {
        $q = AntreanFarmasi::whereDate('tanggal', $tanggal);

        // Antrean batal tidak dihitung di total & sisa; nomornya tidak dipakai ulang.
        return [
            'total' => (clone $q)->where('status', '!=', AntreanFarmasi::BATAL)->count(),
            'sisa' => (clone $q)->where('status', AntreanFarmasi::MENUNGGU)->count(),
            'dipanggil' => (int) (clone $q)->whereIn('status', [AntreanFarmasi::DIPANGGIL, AntreanFarmasi::SELESAI])->max('nomorantrean'),
            'selesai' => (clone $q)->where('status', AntreanFarmasi::SELESAI)->count(),
            'batal' => (clone $q)->where('status', AntreanFarmasi::BATAL)->count(),
        ];
    }

    /* ----------------------------- Pendaftaran ---------------------------- */

    public function cariPasien(string $kata, int $batas = 20): array
    {
        $kata = trim($kata);
        if ($kata === '') {
            return [];
        }

        return PasienUji::query()
            ->where(function ($q) use ($kata) {
                $q->where('norm', $kata)
                    ->orWhere('nomorkartu', $kata)
                    ->orWhere('nik', $kata)
                    ->orWhere('nama', 'like', '%'.addcslashes($kata, '%_\\').'%');
            })
            ->orderBy('nama')
            ->limit($batas)
            ->get()
            ->map(fn (PasienUji $p) => $this->bentukPasien($p))
            ->all();
    }

    public function pasienByNorm(string $norm): ?array
    {
        $p = PasienUji::where('norm', $norm)->first();

        return $p ? $this->bentukPasien($p) : null;
    }

    public function simpanPasien(array $data): array
    {
        return $this->bentukPasien(PasienUji::create([
            'norm' => $data['norm'],
            'nama' => strtoupper($data['nama']),
            'nomorkartu' => $data['nomorkartu'] ?? null,
            'nik' => $data['nik'] ?? null,
            'nohp' => $data['nohp'] ?? null,
            'tanggal_lahir' => $data['tanggal_lahir'] ?? null,
        ]));
    }

    public function daftarJadwal(string $tanggal, ?string $kodepoli = null): array
    {
        $hari = Carbon::parse($tanggal)->dayOfWeekIso;

        $jadwal = $this->jadwalAktif()->where('hari', $hari)
            ->when($kodepoli, fn ($q) => $q->where('kodepoli', $kodepoli))
            ->orderBy('namapoli')->orderBy('jampraktek')->orderBy('namadokter')
            ->get();

        $terisi = AntreanPoliUji::whereDate('tanggal', $tanggal)
            ->where('batal', false)
            ->selectRaw('kodepoli, kodedokter, jampraktek, jenispasien, count(*) as jumlah')
            ->groupBy('kodepoli', 'kodedokter', 'jampraktek', 'jenispasien')
            ->get();

        return $jadwal->map(function (JadwalUji $j) use ($terisi) {
            $milik = $terisi->where('kodepoli', $j->kodepoli)
                ->where('kodedokter', $j->kodedokter)
                ->where('jampraktek', $j->jampraktek);

            return $this->bentukJadwal(
                $j,
                (int) $milik->where('jenispasien', 'JKN')->sum('jumlah'),
                (int) $milik->where('jenispasien', '!=', 'JKN')->sum('jumlah'),
            );
        })->all();
    }

    public function semuaJadwal(): array
    {
        return $this->jadwalAktif()->orderBy('hari')->orderBy('namapoli')->orderBy('jampraktek')->get()
            ->map(fn (JadwalUji $j) => $this->bentukJadwal($j))
            ->all();
    }

    public function jadwalById(string $id, string $tanggal): ?array
    {
        foreach ($this->daftarJadwal($tanggal) as $j) {
            if ($j['id'] === $id) {
                return $j;
            }
        }

        return null;
    }

    public function simpanJadwalSinkron(int $hari, string $kodepoli, array $jadwal): int
    {
        return DB::transaction(function () use ($hari, $kodepoli, $jadwal) {
            JadwalUji::where('sumber', JadwalUji::SUMBER_BPJS)
                ->where('hari', $hari)
                ->where('kodepoli', $kodepoli)
                ->delete();

            $waktu = now();
            foreach ($jadwal as $j) {
                JadwalUji::create([
                    'kodepoli' => $kodepoli,
                    'namapoli' => $j['namapoli'],
                    'kodedokter' => $j['kodedokter'],
                    'namadokter' => $j['namadokter'],
                    'hari' => $hari,
                    'jampraktek' => $j['jampraktek'],
                    'kuota_jkn' => $j['kuota_jkn'],
                    'kuota_nonjkn' => JadwalUji::KUOTA_NONJKN_DEFAULT,
                    'libur' => $j['libur'],
                    'sumber' => JadwalUji::SUMBER_BPJS,
                    'disinkron_at' => $waktu,
                ]);
            }

            return count($jadwal);
        });
    }

    public function catatPoliBerjadwal(string $kodepoli, string $namapoli, string $tanggal): void
    {
        PoliBerjadwal::updateOrCreate(
            ['kodepoli' => $kodepoli],
            ['namapoli' => $namapoli, 'tanggal_jadwal_terakhir' => $tanggal],
        );
    }

    public function daftarPoliBerjadwal(): array
    {
        return PoliBerjadwal::orderBy('kodepoli')->pluck('namapoli', 'kodepoli')->all();
    }

    public function hapusJadwalFiktif(): int
    {
        return JadwalUji::where('sumber', JadwalUji::SUMBER_FIKTIF)->delete();
    }

    public function ringkasanSumberJadwal(): array
    {
        $jumlahBpjs = JadwalUji::where('sumber', JadwalUji::SUMBER_BPJS)->count();
        $terakhir = JadwalUji::where('sumber', JadwalUji::SUMBER_BPJS)->max('disinkron_at');

        return [
            'sumber' => $jumlahBpjs > 0 ? JadwalUji::SUMBER_BPJS : JadwalUji::SUMBER_FIKTIF,
            'jumlah_bpjs' => $jumlahBpjs,
            'jumlah_fiktif' => JadwalUji::where('sumber', JadwalUji::SUMBER_FIKTIF)->count(),
            'terakhir_sinkron' => $terakhir ? Carbon::parse($terakhir)->format('d-m-Y H:i') : null,
        ];
    }

    /**
     * Jadwal yang dipakai pilihan poli & dokter: hasil sinkron BPJS jika ada, selain itu data fiktif.
     * Jadwal libur tidak ikut.
     *
     * @return Builder<JadwalUji>
     */
    private function jadwalAktif(): Builder
    {
        $adaBpjs = JadwalUji::where('sumber', JadwalUji::SUMBER_BPJS)->exists();

        return JadwalUji::query()
            ->where('sumber', $adaBpjs ? JadwalUji::SUMBER_BPJS : JadwalUji::SUMBER_FIKTIF)
            ->where('libur', false);
    }

    public function buatAntreanPoli(array $data): array
    {
        $pasien = $data['pasien'];
        $jadwal = $data['jadwal'];
        $tanggal = $data['tanggal'];

        $poli = DB::transaction(function () use ($data, $pasien, $jadwal, $tanggal) {
            $angka = (int) AntreanPoliUji::whereDate('tanggal', $tanggal)
                ->where('kodepoli', $jadwal['kodepoli'])
                ->lockForUpdate()
                ->max('angkaantrean') + 1;

            $urut = str_pad((string) $angka, 3, '0', STR_PAD_LEFT);
            $kodebooking = Carbon::parse($tanggal)->format('Ymd').strtoupper($jadwal['kodepoli']).$urut;
            if (AntreanPoliUji::where('kodebooking', $kodebooking)->exists()) {
                $kodebooking .= strtoupper(Str::random(3));
            }

            return AntreanPoliUji::create([
                'kodebooking' => $kodebooking,
                'tanggal' => $tanggal,
                'status' => 'terdaftar',
                'norm' => $pasien['norm'],
                'nama' => $pasien['nama'],
                'nomorkartu' => $pasien['nomorkartu'],
                'nik' => $pasien['nik'],
                'nohp' => $pasien['nohp'],
                'jenispasien' => $data['jenispasien'],
                'kodepoli' => $jadwal['kodepoli'],
                'namapoli' => $jadwal['namapoli'],
                'kodedokter' => $jadwal['kodedokter'],
                'namadokter' => $jadwal['namadokter'],
                'jampraktek' => $jadwal['jampraktek'],
                'jeniskunjungan' => $data['jeniskunjungan'],
                'nomorreferensi' => $data['nomorreferensi'],
                'nomorantrean' => strtoupper($jadwal['kodepoli']).'-'.$urut,
                'angkaantrean' => $angka,
                'wajib_fp' => $data['wajib_fp'],
            ]);
        });

        return $this->bentukAntreanPoli($poli);
    }

    /* -------------------------------- Poli -------------------------------- */

    public function antreanPoliLengkap(string $kodebooking): ?array
    {
        $poli = AntreanPoliUji::where('kodebooking', $kodebooking)->first();

        return $poli ? $this->bentukAntreanPoli($poli) : null;
    }

    public function daftarAntreanPoli(string $tanggal, ?string $kodepoli = null, ?string $kodedokter = null): array
    {
        return AntreanPoliUji::whereDate('tanggal', $tanggal)
            ->when($kodepoli, fn ($q) => $q->where('kodepoli', $kodepoli))
            ->when($kodedokter, fn ($q) => $q->where('kodedokter', $kodedokter))
            ->orderBy('kodepoli')->orderBy('angkaantrean')->orderBy('id')
            ->get()
            ->map(fn (AntreanPoliUji $p) => $this->bentukAntreanPoli($p))
            ->all();
    }

    public function ubahStatusAntreanPoli(string $kodebooking, string $status, ?string $jenisresep = null): array
    {
        $poli = AntreanPoliUji::where('kodebooking', $kodebooking)->firstOrFail();

        $ubah = match ($status) {
            'checkin' => ['checkin' => true, 'taskid3_at' => now()],
            'dipanggil' => ['dipanggil_at' => now()],
            'dilayani' => ['taskid4_at' => now()],
            'selesai' => ['taskid5_at' => now(), 'jenisresep' => $jenisresep],
            'batal' => ['batal' => true],
            default => throw new InvalidArgumentException("Status antrean poli tidak dikenal: {$status}"),
        };

        $poli->update($ubah + ['status' => $status]);

        return $this->bentukAntreanPoli($poli->refresh());
    }

    public function tandaiAntreanPoliTerkirim(string $kodebooking): void
    {
        AntreanPoliUji::where('kodebooking', $kodebooking)->update(['dikirim_bpjs_at' => now()]);
    }

    /* ------------------------------- Farmasi ------------------------------ */

    public function daftarSiapAmbilFarmasi(string $tanggal): array
    {
        $sudah = AntreanFarmasi::whereDate('tanggal', $tanggal)->pluck('kodebooking');

        return AntreanPoliUji::whereDate('tanggal', $tanggal)
            ->where('batal', false)
            ->whereNotNull('taskid5_at')
            ->whereIn('jenisresep', ['Racikan', 'Non Racikan'])
            ->whereNotIn('kodebooking', $sudah)
            ->orderBy('taskid5_at')
            ->get()
            ->map(fn (AntreanPoliUji $p) => $this->bentukAntreanPoli($p))
            ->all();
    }

    public function daftarAntreanFarmasi(string $tanggal): array
    {
        $farmasi = AntreanFarmasi::whereDate('tanggal', $tanggal)->orderBy('nomorantrean')->get();
        $poli = AntreanPoliUji::whereIn('kodebooking', $farmasi->pluck('kodebooking'))->get()->keyBy('kodebooking');

        $jam = fn ($w) => $w?->format('H:i');

        return $farmasi->map(fn (AntreanFarmasi $f) => [
            'kodebooking' => $f->kodebooking,
            'tanggal' => $f->tanggal->format('Y-m-d'),
            'nomorantrean' => (int) $f->nomorantrean,
            'jenisresep' => $f->jenisresep,
            'status' => $f->status,
            'keterangan' => $f->keterangan,
            'dikirim_bpjs' => $f->dikirim_bpjs_at !== null,
            'sumber' => (string) $f->sumber,
            'nama' => $f->nama ?? ($poli[$f->kodebooking]->nama ?? null),
            'norm' => $f->norm ?? ($poli[$f->kodebooking]->norm ?? null),
            'namapoli' => $poli[$f->kodebooking]->namapoli ?? ($poli[$f->kodebooking]->kodepoli ?? null),
            'diambil_at' => $jam($f->created_at),
            'dipanggil_at' => $jam($f->dipanggil_at),
            'selesai_at' => $jam($f->selesai_at),
            'batal_at' => $jam($f->batal_at),
            'task6_at' => $jam($f->task6_at),
            'task6_pesan' => $f->task6_pesan,
            'task7_at' => $jam($f->task7_at),
            'task7_pesan' => $f->task7_pesan,
        ])->all();
    }

    public function ubahStatusFarmasi(string $kodebooking, string $tanggal, string $status): void
    {
        $f = AntreanFarmasi::where('kodebooking', $kodebooking)->whereDate('tanggal', $tanggal)->first();
        if (! $f) {
            return;
        }

        $f->status = $status;
        // Waktu dipanggil pertama kali dipertahankan saat panggil ulang.
        match ($status) {
            AntreanFarmasi::DIPANGGIL => $f->dipanggil_at ??= now(),
            AntreanFarmasi::SELESAI => $f->selesai_at = now(),
            AntreanFarmasi::BATAL => $f->batal_at = now(),
            default => null,
        };
        $f->save();
    }

    public function tandaiTaskFarmasi(string $kodebooking, string $tanggal, int $taskid, bool $berhasil, ?string $pesan = null): void
    {
        if (! in_array($taskid, [6, 7], true)) {
            throw new InvalidArgumentException('Task ID farmasi hanya 6 atau 7.');
        }

        AntreanFarmasi::where('kodebooking', $kodebooking)->whereDate('tanggal', $tanggal)->update([
            "task{$taskid}_at" => $berhasil ? now() : null,
            "task{$taskid}_pesan" => $berhasil ? null : mb_substr((string) $pesan, 0, 200),
        ]);
    }

    /* -------------------------------- Resep ------------------------------- */

    public function simpanResep(array $data, string $sumber): array
    {
        $resep = Resep::updateOrCreate(['kodebooking' => $data['kodebooking']], [
            'jenisresep' => $data['jenisresep'],
            'norekammedis' => $data['norekammedis'] ?? null,
            'namapasien' => isset($data['namapasien']) ? mb_strtoupper(trim((string) $data['namapasien'])) : null,
            'nomorkartu' => $data['nomorkartu'] ?? null,
            'kodepoli' => isset($data['kodepoli']) ? strtoupper((string) $data['kodepoli']) : null,
            'tanggal' => $data['tanggal'] ?? now()->toDateString(),
            'sumber' => $sumber,
            'dibatalkan_at' => null,
        ]);

        return $this->bentukResep($resep);
    }

    public function cariResep(string $kodebooking): ?array
    {
        $resep = Resep::where('kodebooking', $kodebooking)->first();

        return $resep ? $this->bentukResep($resep) : null;
    }

    public function batalkanResep(string $kodebooking): ?array
    {
        $resep = Resep::where('kodebooking', $kodebooking)->first();
        if (! $resep) {
            return null;
        }
        $resep->update(['dibatalkan_at' => $resep->dibatalkan_at ?? now()]);

        return $this->bentukResep($resep);
    }

    /** @return array<string, mixed> */
    protected function bentukResep(Resep $r): array
    {
        return [
            'kodebooking' => $r->kodebooking,
            'jenisresep' => $r->jenisresep,
            'norekammedis' => $r->norekammedis,
            'namapasien' => $r->namapasien,
            'nomorkartu' => $r->nomorkartu,
            'kodepoli' => $r->kodepoli,
            'tanggal' => $r->tanggal->format('Y-m-d'),
            'sumber' => $r->sumber,
            'dibatalkan' => $r->dibatalkan_at !== null,
        ];
    }

    public function tandaiFarmasiTerkirim(string $kodebooking, string $tanggal): void
    {
        AntreanFarmasi::where('kodebooking', $kodebooking)->whereDate('tanggal', $tanggal)->update(['dikirim_bpjs_at' => now()]);
    }

    /* ------------------------------- Bentuk ------------------------------- */

    /** @return array{norm: string, nama: string, nomorkartu: ?string, nik: ?string, nohp: ?string, tanggal_lahir: ?string} */
    private function bentukPasien(PasienUji $p): array
    {
        return [
            'norm' => $p->norm,
            'nama' => $p->nama,
            'nomorkartu' => $p->nomorkartu,
            'nik' => $p->nik,
            'nohp' => $p->nohp,
            'tanggal_lahir' => $p->tanggal_lahir?->format('Y-m-d'),
        ];
    }

    /** @return array<string, mixed> */
    private function bentukJadwal(JadwalUji $j, int $terisiJkn = 0, int $terisiNonJkn = 0): array
    {
        return [
            'id' => (string) $j->id,
            'kodepoli' => $j->kodepoli,
            'namapoli' => $j->namapoli,
            'kodedokter' => $j->kodedokter,
            'namadokter' => $j->namadokter,
            'hari' => (int) $j->hari,
            'jampraktek' => $j->jampraktek,
            'kuota_jkn' => (int) $j->kuota_jkn,
            'kuota_nonjkn' => (int) $j->kuota_nonjkn,
            'terisi_jkn' => $terisiJkn,
            'terisi_nonjkn' => $terisiNonJkn,
            'sumber' => (string) $j->sumber,
        ];
    }

    /** @return array<string, mixed> */
    private function bentukAntreanPoli(AntreanPoliUji $p): array
    {
        return [
            'kodebooking' => $p->kodebooking,
            'tanggal' => $p->tanggal->format('Y-m-d'),
            'nomorantrean' => $p->nomorantrean,
            'angkaantrean' => $p->angkaantrean,
            'norm' => $p->norm,
            'nama' => $p->nama,
            'nomorkartu' => $p->nomorkartu,
            'nik' => $p->nik,
            'nohp' => $p->nohp,
            'jenispasien' => (string) ($p->jenispasien ?: 'JKN'),
            'kodepoli' => $p->kodepoli,
            'namapoli' => $p->namapoli,
            'kodedokter' => $p->kodedokter,
            'namadokter' => $p->namadokter,
            'jampraktek' => $p->jampraktek,
            'jeniskunjungan' => $p->jeniskunjungan,
            'nomorreferensi' => $p->nomorreferensi,
            'status' => $p->statusEfektif(),
            'wajib_fp' => (bool) $p->wajib_fp,
            'jenisresep' => $p->jenisresep,
            'checkin_at' => $p->taskid3_at?->format('H:i'),
            'dipanggil_at' => $p->dipanggil_at?->format('H:i'),
            'mulai_at' => $p->taskid4_at?->format('H:i'),
            'selesai_at' => $p->taskid5_at?->format('H:i'),
            'dikirim_bpjs' => $p->dikirim_bpjs_at !== null,
        ];
    }
}
