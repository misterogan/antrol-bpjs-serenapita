# Catatan kesepakatan dengan vendor SIMRS (Dhealth)

Dokumen internal RS. Tidak ditampilkan di Swagger UI (`/antrol/api-docs`). Spesifikasi API untuk vendor ada di `docs/openapi.yaml`.

## Hal yang perlu disepakati

1. **Waktu pemanggilan `POST /jkn/internal/resep` dan nilai `jenisresep`**
   - Dipanggil saat dokter menyelesaikan pelayanan poli, bersamaan dengan Update Waktu task ID 5 yang dikirim SIMRS ke BPJS.
   - Nilai `jenisresep`: `Racikan`, `Non Racikan`, atau `Tidak ada`.
   - Kiriman ulang dengan kode booking yang sama memperbarui data.
   - Tanpa data resep, pengambilan antrean farmasi ditolak dengan pesan "Jenis resep belum tersedia, silakan hubungi farmasi".
   - Pembatalan resep memakai `POST /jkn/internal/resep/{kodebooking}/batal`.

2. **Pemeriksaan token WSRS lewat service vendor**
   - Pada mode token vendor (`ANTROL_WSRS_TOKEN_MODE=vendor`), modul meneruskan header `x-token` dan `x-username` dari BPJS ke `POST {alamat vendor}/jkn/sisa-antrian` dengan body `{"kodebooking": "CEKTOKEN"}`.
   - Service tersebut hanya membaca data.
   - Perlu dikonfirmasi: bentuk jawaban vendor untuk token kedaluwarsa. Pola `expired` di `config/antrol.php` masih sementara, disamakan dengan token tidak valid (401 "Unauthorized!").

3. **Task ID 6 dan 7 farmasi dikirim oleh modul**
   - Modul mengirim Update Waktu task ID 6 saat petugas farmasi menekan Panggil dan task ID 7 saat Selesai.
   - SIMRS vendor **tidak** mengirim task ID 6 dan 7, agar tidak ganda.

4. **Pesan poli fingerprint di layar pendaftaran SIMRS vendor**
   - Memakai `GET /jkn/internal/poli-fingerprint/{kodepoli}`.
   - Jika jawabannya code 201 (referensi BPJS gagal dibaca), layar pendaftaran sebaiknya tidak menganggap poli bebas fingerprint.

5. **Alamat jaringan lokal dan pembagian kunci API**
   - Alamat LAN modul yang dipanggil SIMRS vendor.
   - Alamat LAN service vendor untuk pemeriksaan token (`ANTROL_VENDOR_BASE_URL`).
   - Alamat IP server SIMRS vendor untuk `ANTROL_INTERNAL_IP_ALLOWLIST`.
   - Cara menyerahkan `ANTROL_INTERNAL_API_KEY` kepada vendor (tidak lewat email atau chat biasa) dan cara menggantinya bila bocor.

6. **Kontak penanggung jawab** di RS dan di vendor, untuk diisi di `info.description` `docs/openapi.yaml` (saat ini `[isi kontak RS]`).
