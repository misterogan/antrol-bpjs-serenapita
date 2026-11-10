# Modul Antrean Online: item merah UAT (Laravel)

Modul tambahan untuk menutup 7 item yang masih merah di Form UAT Bridging Antrean Online 2.0:

| Item form UAT | Di modul ini |
|---|---|
| WSRS Service Add Antrean Farmasi | `POST /jkn/ambil-antrean-farmasi` |
| WSRS Service Status Antrean Farmasi | `POST /jkn/status-antrean-farmasi` |
| WSBPJS Service Referensi Poli Fingerprint | Antrian Pendaftaran Onsite BPJS → tab Cek Poli Fingerprint |
| WSBPJS Service Referensi Pasien Fingerprint | Antrian Pendaftaran Onsite BPJS → tab Cek Peserta Fingerprint |
| WSBPJS Service Tambah Antrean Farmasi | Antrian Farmasi → panel Ambil antrean farmasi (pasien onsite) |
| WSBPJS Service Antrean Belum Dilayani | Monitoring Antrean |
| WSBPJS Service Antrean Belum Dilayani Per Poli Per Dokter Per Hari Per Jam Praktek | Monitoring Antrean (pilihan dari jadwal) |
| Pesan poli fingerprint (Simulasi SIM FKRTL) | Pendaftaran, langkah konfirmasi sebelum simpan (partial `antrol.partials.pesan-fingerprint`) |

## Alur halaman (`/antrol`)

Semua halaman dipilih dari kartu di **Daftar Antrian** (`/antrol`); setiap halaman punya tombol kembali.

| Kartu | Isi |
|---|---|
| Antrian Pendaftaran Onsite BPJS | Tab **Cek Poli Fingerprint** (item 3), **Cek Peserta Fingerprint** (item 4), dan **Pendaftaran (simulasi)** `/antrol/pendaftaran`. |
| Antrian Farmasi `/antrol/farmasi` | Ringkasan (sama dengan WSRS Status Antrean Farmasi), tabel antrean hari ini (onsite & Mobile JKN, diperbarui tiap 10 detik), Panggil/Selesai (task ID 6/7 ke BPJS), Batalkan, ambil antrean onsite (Tambah Antrean Farmasi), input resep manual. |
| Monitoring Antrean `/antrol/monitoring` | Antrean belum dilayani, semua atau per poli/dokter/hari/jam praktek (item 6 dan 7). |
| Log Bridging `/antrol/log` | Log WSBPJS, WSRS, vendor (cek token), dan API internal, dengan detail request/response. |
| Alat Uji `/antrol/alat-uji` | Poli (simulasi), Data uji & sinkron jadwal, Uji service. |
| Dokumentasi API `/antrol/api-docs` | Swagger UI dari `docs/openapi.yaml`. |

### Mode simulasi (`ANTROL_MODE_SIMULASI`, default `false`)

Khusus BPJS development, supaya alur bisa diuji tanpa SIMRS. Jika `true`, pendaftaran juga mengirim Tambah Antrean, dan halaman Poli mengirim Update Waktu task ID 3 (check in), 4 (mulai), dan 5 (selesai, beserta jenis resep). Header menampilkan label **MODE SIMULASI - DEVELOPMENT**. Jika `false`, keduanya tidak dikirim karena itu tugas SIMRS utama. Tambah Antrean Farmasi tetap dikirim di kedua mode.

Path dan format body kedua service itu ada di `config/antrol.php` (`paths.tambah_antrean`, `paths.update_waktu`, `body.*`), ditandai `// VERIFIKASI DENGAN KATALOG`.

Uji dari sisi Mobile JKN (item 1 dan 2) dilakukan dengan Postman (`postman_wsrs_antrean_farmasi.json`).

### Sumber data (`ANTROL_SUMBER_DATA`)

- `lokal` (default): tabel data uji modul ini, untuk pengujian.
- `bpjs`: sebelum antrean farmasi dibuat (dari WSRS maupun onsite), modul memeriksa BPJS Antrean Per Kode Booking dan List Waktu Task ID, serta data resep yang dikirim SIMRS vendor lewat `POST /jkn/internal/resep`. Modul tidak terhubung langsung ke database SIMRS vendor.

## Kebutuhan

- Laravel 11 atau lebih baru (proyek ini Laravel 13), PHP 8.2+ dengan ekstensi `openssl` dan `mbstring`
- Paket LZString untuk dekompresi respons BPJS:

```bash
composer require nullpunkt/lz-string-php
```

## Pemasangan

1. Salin folder `app/`, `config/`, `database/`, `resources/`, `routes/` ke proyek Laravel.
2. Daftarkan provider:
   - Laravel 11: tambahkan `App\Providers\AntrolServiceProvider::class` di `bootstrap/providers.php`
   - Laravel 10: tambahkan di array `providers` pada `config/app.php`
3. Isi `.env`:

```env
ANTROL_BASE_URL=https://apijkn-dev.bpjs-kesehatan.go.id/antreanrs_dev
ANTROL_CONS_ID=
ANTROL_SECRET_KEY=
ANTROL_USER_KEY=
ANTROL_WSRS_USERNAME=
ANTROL_WSRS_PASSWORD=
ANTROL_WSRS_TOKEN_TTL=60
# Opsional, default di bawah (URL WSRS = https://domain-rs/{prefix}/{path})
ANTROL_WSRS_PREFIX=jkn
ANTROL_WSRS_PATH_AUTH=auth
ANTROL_WSRS_PATH_AMBIL_FARMASI=ambil-antrean-farmasi
ANTROL_WSRS_PATH_STATUS_FARMASI=status-antrean-farmasi
# Pemeriksaan token WSRS: lokal (token dari /jkn/auth modul ini) atau vendor
ANTROL_WSRS_TOKEN_MODE=lokal
ANTROL_VENDOR_BASE_URL=
ANTROL_VENDOR_TIMEOUT=5
ANTROL_VENDOR_TOKEN_CACHE_MENIT=2
ANTROL_SUMBER_DATA=lokal
ANTROL_MODE_SIMULASI=false
```

4. Jalankan `php artisan migrate`, lalu isi contoh pasien dan jadwal dokter: `php artisan db:seed --class=AntrolDataUjiSeeder`. Ganti kode poli/dokter di seeder dengan kode HFIS RS jika mode simulasi dipakai.
5. Tambahkan middleware login pada `routes/antrol_web.php` (baris `->middleware([])`), misalnya `['auth']`, agar halaman tidak terbuka untuk umum.

## Cara uji dan capture

### WSRS (Postman)

URL WSRS mengikuti pola WSRS RS yang terdaftar di BPJS (mis. `https://mjkn.rsuserenapita.id/jkn/ambil-antrian`). Default-nya:

| Service | URL |
|---|---|
| Token | `GET https://domain-rs/jkn/auth` (header `x-username`, `x-password`) |
| Ambil antrean farmasi | `POST https://domain-rs/jkn/ambil-antrean-farmasi` (header `x-token`, `x-username`) |
| Status antrean farmasi | `POST https://domain-rs/jkn/status-antrean-farmasi` (header `x-token`, `x-username`) |

Awalan dan nama path bisa diubah di `config/antrol.php` (`wsrs.prefix`, `wsrs.paths.*`) atau lewat env di atas. Awalan tidak boleh kosong atau `antrol` karena dipakai halaman web modul ini. Jika memakai `php artisan route:cache`, jalankan ulang setelah mengubahnya. URL lama `/api/wsrs/...` sudah tidak tersedia.

**Mode token vendor** (`ANTROL_WSRS_TOKEN_MODE=vendor`): BPJS memakai token dari Get Token SIMRS vendor. Setiap token diperiksa ke `POST {ANTROL_VENDOR_BASE_URL}/jkn/sisa-antrian` (body `{"kodebooking":"CEKTOKEN"}`, header `x-token` dan `x-username` diteruskan). Route `/jkn/auth` modul ini menjawab 404. Isi pola jawaban vendor di `config/antrol.php` (`wsrs.vendor.pola`, bertanda `VERIFIKASI DENGAN VENDOR`). Selama pola `expired` dan `tidak_valid` belum diisi, semua token ditolak dengan "Token tidak dapat divalidasi". Hal yang sama berlaku jika vendor tidak dapat dihubungi, timeout, atau jawabannya tidak dikenali. Token sah disimpan di cache sebagai hash selama `ANTROL_VENDOR_TOKEN_CACHE_MENIT`. Setiap pemeriksaan tercatat di Log bridging dengan arah Vendor, dengan token disamarkan.

Impor `postman_wsrs_antrean_farmasi.json`, isi variabel `base_url` (mis. `https://domain-rs/jkn`), `username`, `password`, dan `kodebooking`. Request **Get Token** otomatis menyimpan token untuk request berikutnya.

Siapkan data lewat alur Pendaftaran → Poli, atau di halaman **Uji service → Data uji WSRS** (`/antrol/data-uji`) untuk tiap skenario form UAT:

| Skenario form UAT | Data uji yang dibuat |
|---|---|
| Pengecekan kodebooking tidak ditemukan | Kirim kode booking yang tidak ada |
| Pengambilan antrean farmasi | Tanggal hari ini, task ID 5 dicentang, jenis resep Racikan/Non Racikan |
| Nomor antrean bertambah | Buat 2 data siap ambil, ambil berurutan |
| 1 kode booking 1 antrean per hari | Ambil dua kali dengan kode booking yang sama |
| Status antrean farmasi | Ambil antrean, lalu panggil/selesaikan di tabel farmasi agar nilai antreanpanggil dan sisaantrean berubah |

### WSBPJS (halaman `/antrol/uji-service`)

Setiap tombol menampilkan request, metadata, dan response yang sudah didekripsi. Capture layar ini untuk form UAT. Semua panggilan juga tercatat di halaman Log bridging.

Untuk **Tambah Antrean Farmasi**, kosongkan nomor antrean: modul mengambil nomor dari antrean farmasi RS (dengan validasi task ID 5 dan jenis resep), lalu mengirimnya ke BPJS. Kode booking harus kode booking poli yang memang terdaftar di BPJS (development) dan sudah di-update task ID 5 beserta jenis resep lewat service Update Waktu SIMRS.

## Integrasi dengan SIMRS vendor

API internal `/jkn/internal/*` (kunci `x-api-key`, IP allowlist, rate limit) dijelaskan di `docs/openapi.yaml` dan Swagger UI `/antrol/api-docs`; koleksi Postman di `docs/postman-api-internal-vendor.json`. Hal yang perlu disepakati dengan vendor tercantum di bagian info spesifikasi tersebut.

## Yang perlu dicocokkan dengan katalog BPJS terbaru

Path endpoint dan nama field disusun dari katalog Antrean RS dan form UAT 2023. Sebelum UAT, cocokkan dengan katalog terbaru di portal Trustmark BPJS, terutama:

- Path di `config/antrol.php` bagian `paths`
- Nilai `jenis` pada referensi pasien fingerprint (`nik` / `noka`)
- Nama field kode poli pada respons referensi poli fingerprint (modul membaca `kodepoli`, `kdpoli`, atau `kodesubspesialis`)
- Format nilai `jenisresep` pada Tambah Antrean Farmasi (modul mengirim `racikan` / `non racikan`)
- Format `hari` dan `jampraktek` pada Antrean Belum Dilayani per poli
- Nilai `metadata.code` yang berarti sukses (`kode_sukses`; log development menunjukkan Referensi Poli Fingerprint menjawab `1`)
- Mode simulasi: path dan field body Tambah Antrean dan Update Waktu, serta nilai `jenisresep` pada task ID 5

Semua path bisa diubah di config tanpa mengubah kode.

## Struktur

```
app/
  Http/Controllers/Antrol/   PendaftaranController, PoliController, FarmasiController,
                             MonitoringController, LogController,
                             WsrsController, WsbpjsController, DataUjiController (uji service)
  Http/Middleware/           WsrsToken (validasi x-token, x-username)
  Models/Antrol/             AntreanPoliUji, PasienUji, JadwalUji, AntreanFarmasi, WsrsToken, AntrolLog
  Providers/                 AntrolServiceProvider
  Repositories/              AntreanRepository (kontrak), LocalAntreanRepository, BpjsAntreanRepository
  Services/Antrol/           AntrolClient (signature + dekripsi), WsbpjsService,
                             AntreanFarmasiService (aturan UAT), PelayananService (alur), Hasil
config/antrol.php
database/migrations/         tabel antrol_*
resources/views/antrol/      pendaftaran, poli, farmasi, monitoring, log, uji service, partial
lang/id/validation.php       pesan validasi Bahasa Indonesia
tests/Feature/Antrol/        feature test alur (Http::fake)
routes/                      antrol_api.php (WSRS), antrol_web.php (halaman)
postman_wsrs_antrean_farmasi.json
```
