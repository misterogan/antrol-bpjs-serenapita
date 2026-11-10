<?php

return [

    // Identitas RS di header halaman modul. Logo dibaca dari public/{logo}; jika file tidak ada,
    // header menampilkan placeholder.
    'nama_rs' => env('ANTROL_NAMA_RS', 'RSU SERENAPITA'),
    'logo' => env('ANTROL_LOGO', 'img/logo-rs.png'),

    /*
    |--------------------------------------------------------------------------
    | Kredensial WS BPJS (Antrean RS)
    |--------------------------------------------------------------------------
    | Development : https://apijkn-dev.bpjs-kesehatan.go.id/antreanrs_dev
    | Production  : https://apijkn.bpjs-kesehatan.go.id/antreanrs
    | Cocokkan dengan katalog bridging terbaru dari BPJS.
    */
    'bpjs' => [
        'base_url' => env('ANTROL_BASE_URL', 'https://apijkn-dev.bpjs-kesehatan.go.id/antreanrs_dev'),
        'cons_id' => env('ANTROL_CONS_ID'),
        'secret' => env('ANTROL_SECRET_KEY'),
        'user_key' => env('ANTROL_USER_KEY'),
        'timeout' => (int) env('ANTROL_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Path endpoint WS BPJS
    |--------------------------------------------------------------------------
    | Disimpan di config supaya mudah disesuaikan jika katalog berubah.
    */
    'paths' => [
        'ref_poli_fp' => '/ref/poli/fp',
        'ref_pasien_fp' => '/ref/pasien/fp/identitas/{jenis}/noidentitas/{nomor}',
        'tambah_farmasi' => '/antrean/farmasi/add',
        'belum_dilayani' => '/antrean/pendaftaran/aktif',
        'belum_dilayani_poli' => '/antrean/pendaftaran/kodepoli/{kodepoli}/kodedokter/{kodedokter}/hari/{hari}/jampraktek/{jampraktek}',

        // Dua path di bawah hanya dipakai saat mode simulasi (lihat 'mode_simulasi').
        'tambah_antrean' => '/antrean/add',          // VERIFIKASI DENGAN KATALOG
        'update_waktu' => '/antrean/updatewaktu',  // VERIFIKASI DENGAN KATALOG

        // Halaman Uji service, service yang sudah lolos UAT.
        'antrean_kodebooking' => '/antrean/pendaftaran/kodebooking/{kodebooking}', // VERIFIKASI DENGAN KATALOG
        'list_task' => '/antrean/getlisttask', // VERIFIKASI DENGAN KATALOG (POST, body {"kodebooking": "..."})

        // Sinkron jadwal dari BPJS (halaman Data uji). Tanggal dikirim dengan format Y-m-d.
        'ref_poli' => '/ref/poli', // VERIFIKASI DENGAN KATALOG
        'ref_jadwal_dokter' => '/jadwaldokter/kodepoli/{kodepoli}/tanggal/{tanggal}', // VERIFIKASI DENGAN KATALOG
    ],

    /*
    |--------------------------------------------------------------------------
    | Nama field respons referensi untuk sinkron jadwal
    |--------------------------------------------------------------------------
    | Kunci = arti field di modul ini, nilai = daftar nama field di respons BPJS
    | (dicoba berurutan, yang pertama terisi dipakai).
    */
    'respons' => [
        // VERIFIKASI DENGAN KATALOG
        'ref_poli' => [
            'kodepoli' => ['kdsubspesialis', 'kodesubspesialis', 'kdpoli', 'kodepoli'],
            'namapoli' => ['nmsubspesialis', 'namasubspesialis', 'nmpoli', 'namapoli'],
        ],

        // VERIFIKASI DENGAN KATALOG (jam praktek "08:00 - 12:00" disimpan tanpa spasi)
        'ref_jadwal_dokter' => [
            'kodepoli' => ['kodesubspesialis', 'kdsubspesialis', 'kodepoli', 'kdpoli'],
            'namapoli' => ['namasubspesialis', 'nmsubspesialis', 'namapoli', 'nmpoli'],
            'kodedokter' => ['kodedokter'],
            'namadokter' => ['namadokter'],
            'jampraktek' => ['jadwal', 'jampraktek'],
            'kuota' => ['kapasitaspasien', 'kuotajkn'],
            'libur' => ['libur'],
        ],

        // VERIFIKASI DENGAN KATALOG: nama field respons Referensi Pasien Fingerprint (log development baru
        // memuat jawaban code 0 "Peserta tidak terdaftar"). tgllahir dalam milidetik.
        'ref_pasien_fp' => [
            'nik' => ['nik'],
            'nomorkartu' => ['nomorkartu', 'noka'],
            'tgllahir' => ['tgllahir', 'tanggallahir'],
            'daftarfp' => ['daftarfp'],
        ],

        // VERIFIKASI DENGAN KATALOG: arti nilai daftarfp.
        'keterangan_daftarfp' => [
            '1' => 'Sudah terdaftar fingerprint',
            '0' => 'Belum terdaftar fingerprint',
        ],

        // VERIFIKASI DENGAN KATALOG: Antrean Per Kode Booking (mode sumber data 'bpjs').
        // 'tidak_ditemukan' = code DAN pesan yang berarti kode booking tidak ada di BPJS.
        'antrean_kodebooking' => [
            'tanggal' => ['tanggal'],
            'status' => ['status'],
            'norekammedis' => ['norekammedis'],
            'tidak_ditemukan' => ['code' => [201, 204], 'pesan' => ['tidak ditemukan', 'no content', 'tidak ada']],
        ],

        // VERIFIKASI DENGAN KATALOG: List Waktu Task ID (mode sumber data 'bpjs').
        'list_task' => [
            'taskid' => ['taskid'],
            'tidak_ditemukan' => ['code' => [201, 204], 'pesan' => ['tidak ditemukan', 'no content', 'tidak ada']],
        ],

        // VERIFIKASI DENGAN KATALOG: jawaban Referensi Jadwal Dokter yang berarti "poli tidak punya jadwal".
        // Harus cocok code DAN pesan (tidak peka huruf besar-kecil, cukup mengandung salah satu),
        // karena code 201 juga dipakai BPJS untuk galat lain. Log development: 201 "No Content".
        'tidak_ada_jadwal' => [
            'code' => [201],
            'pesan' => ['no content', 'tidak ditemukan', 'tidak ada'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sinkron jadwal dari BPJS
    |--------------------------------------------------------------------------
    | jeda_ms             : jeda antar-panggilan supaya tidak membebani server BPJS.
    | batas_gagal_koneksi : sinkron dihentikan setelah sekian kali gagal koneksi
    |                       berturut-turut (0 = tidak pernah dihentikan).
    */
    'sinkron_jadwal' => [
        'jeda_ms' => (int) env('ANTROL_JEDA_SINKRON_MS', 100),
        'batas_gagal_koneksi' => (int) env('ANTROL_BATAS_GAGAL_KONEKSI', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Format body service yang dipakai mode simulasi
    |--------------------------------------------------------------------------
    | Daftar nama field yang dikirim, berurutan. Field yang tidak tersedia
    | nilainya tidak dikirim. Jenis resep pada Update Waktu hanya disertakan
    | pada task ID 5.
    */
    'body' => [
        // VERIFIKASI DENGAN KATALOG
        'tambah_antrean' => [
            'kodebooking', 'jenispasien', 'nomorkartu', 'nik', 'nohp', 'kodepoli', 'namapoli',
            'pasienbaru', 'norm', 'tanggalperiksa', 'kodedokter', 'namadokter', 'jampraktek',
            'jeniskunjungan', 'nomorreferensi', 'nomorantrean', 'angkaantrean', 'estimasidilayani',
            'sisakuotajkn', 'kuotajkn', 'sisakuotanonjkn', 'kuotanonjkn', 'keterangan',
        ],

        // VERIFIKASI DENGAN KATALOG ('waktu' dalam milidetik)
        'update_waktu' => ['kodebooking', 'taskid', 'waktu', 'jenisresep'],

        // VERIFIKASI DENGAN KATALOG: nilai jenis resep pada Update Waktu task ID 5.
        'jenisresep_update_waktu' => [
            'Racikan' => 'Racikan',
            'Non Racikan' => 'Non racikan',
            'Tidak ada' => 'Tidak ada',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mode simulasi end-to-end (khusus BPJS development)
    |--------------------------------------------------------------------------
    | true  : pendaftaran juga mengirim Tambah Antrean, dan halaman Poli mengirim
    |         Update Waktu task ID 3 (check in), 4 (mulai), 5 (selesai + jenis resep).
    | false : tidak ada pengiriman Tambah Antrean maupun task ID, karena itu
    |         tugas SIMRS utama. Tambah Antrean Farmasi tetap dikirim.
    */
    'mode_simulasi' => (bool) env('ANTROL_MODE_SIMULASI', false),

    /*
    | Alat simulasi: tab "Pendaftaran (simulasi)", halaman Poli (simulasi), dan Data uji.
    | false (default, untuk UAT/production): alat disembunyikan, route-nya menjawab 404, dan
    |        mode_simulasi diabaikan (dianggap false). Input resep manual di Antrian Farmasi tetap ada.
    | true : semua alat tampil, header menampilkan label "ALAT SIMULASI AKTIF".
    */
    'tampilkan_alat_simulasi' => (bool) env('ANTROL_TAMPILKAN_ALAT_SIMULASI', false),

    // Nilai metadata.code yang berarti sukses. Log BPJS development menunjukkan
    // Referensi Poli Fingerprint menjawab code 1 ("OK"), sedangkan service lain 200.
    'kode_sukses' => [200, 1], // VERIFIKASI DENGAN KATALOG

    // Keterangan Tambah Antrean Farmasi jika tidak diisi. BPJS menolak keterangan kosong
    // ("data keterangan belum sesuai"). Tidak berlaku untuk respons WSRS (keterangan tetap "").
    'keterangan_farmasi_default' => env('ANTROL_KETERANGAN_FARMASI', 'Antrean farmasi diambil di rumah sakit'),

    // Perkiraan lama pelayanan per pasien (menit), untuk estimasi dilayani.
    'menit_per_pasien' => (int) env('ANTROL_MENIT_PER_PASIEN', 6),

    /*
    |--------------------------------------------------------------------------
    | WSRS (service milik RS yang dipanggil BPJS / Mobile JKN)
    |--------------------------------------------------------------------------
    | Username & password yang RS berikan ke BPJS.
    */
    'wsrs' => [
        'username' => env('ANTROL_WSRS_USERNAME'),
        'password' => env('ANTROL_WSRS_PASSWORD'),
        'token_ttl_minutes' => (int) env('ANTROL_WSRS_TOKEN_TTL', 60),

        // URL WSRS = https://domain-rs/{prefix}/{path}, mengikuti pola WSRS RS yang terdaftar di BPJS
        // (mis. https://mjkn.rsuserenapita.id/jkn/ambil-antrian). Default:
        //   GET  /jkn/auth
        //   POST /jkn/ambil-antrean-farmasi
        //   POST /jkn/status-antrean-farmasi
        // Prefix tidak boleh kosong atau "antrol" (dipakai halaman web modul ini).
        'prefix' => env('ANTROL_WSRS_PREFIX', 'jkn'),
        'paths' => [
            'auth' => env('ANTROL_WSRS_PATH_AUTH', 'auth'),
            'ambil_farmasi' => env('ANTROL_WSRS_PATH_AMBIL_FARMASI', 'ambil-antrean-farmasi'),
            'status_farmasi' => env('ANTROL_WSRS_PATH_STATUS_FARMASI', 'status-antrean-farmasi'),
        ],

        // Pemeriksaan header x-token pada ambil & status antrean farmasi:
        //   lokal  : token dari GET /jkn/auth milik modul ini.
        //   vendor : token dari Get Token SIMRS vendor, diperiksa ke service vendor (lihat 'vendor').
        //            Route /jkn/auth milik modul menjawab 404.
        'token_mode' => env('ANTROL_WSRS_TOKEN_MODE', 'lokal'),

        // Penyedia WSRS Ambil & Status Antrean Farmasi yang dipanggil BPJS:
        //   vendor : SIMRS vendor (BPJS memanggil vendor langsung). Route WSRS lokal di atas tetap ada
        //            sebagai cadangan, tetapi menjawab 404.
        //   lokal  : route WSRS milik modul ini melayani BPJS.
        'provider' => env('ANTROL_WSRS_PROVIDER', 'vendor'),

        /*
         | Mode vendor: header x-token & x-username dari BPJS diteruskan ke
         | POST {base_url}{path_cek_token} body {"kodebooking": kodebooking_cek} (hanya membaca data).
         | Gagal terhubung, timeout, HTTP 5xx, atau jawaban tak dikenal = token DITOLAK.
         */
        'vendor' => [
            'base_url' => env('ANTROL_VENDOR_BASE_URL'), // sebaiknya alamat internal LAN
            'path_cek_token' => '/jkn/sisa-antrian',
            'kodebooking_cek' => 'CEKTOKEN',
            'timeout' => (int) env('ANTROL_VENDOR_TIMEOUT', 5), // detik, juga batas waktu koneksi
            'token_cache_menit' => (int) env('ANTROL_VENDOR_TOKEN_CACHE_MENIT', 2), // hanya token sah, disimpan sebagai hash

            // Klasifikasi jawaban vendor: cocok jika metadata.code termasuk 'code' DAN metadata.message
            // mengandung salah satu 'pesan' (tidak peka huruf besar-kecil). Sebuah pola dianggap terisi
            // jika 'code' dan 'pesan' sama-sama tidak kosong.
            // Selama pola 'expired' atau 'tidak_valid' belum terisi, SEMUA token ditolak.
            // Jika pola 'sah' terisi, hanya jawaban yang cocok yang dianggap sah (allow-list).
            // Jika jawaban cocok dengan 'expired' dan 'tidak_valid' sekaligus, hasilnya tidak_valid.
            // Token yang exp JWT-nya sudah lewat ditolak "Token Expired" sebelum vendor dipanggil.
            'pola' => [
                // VERIFIKASI DENGAN VENDOR: belum ada contoh asli; sementara sama dengan tidak_valid.
                'expired' => ['code' => [401], 'pesan' => ['Unauthorized']],
                // Jawaban asli: {"response":{"message":"Unauthorized!","status":401},"metadata":{"message":"Unauthorized!","code":401}}
                'tidak_valid' => ['code' => [401], 'pesan' => ['Unauthorized']],
                // Jawaban asli: {"response":{"title":"Proses Gagal!","text":"Antrean Tidak Ditemukan"},"metadata":{"message":"Antrean Tidak Ditemukan","code":201}}
                'sah' => ['code' => [201], 'pesan' => ['Antrean Tidak Ditemukan']],
            ],

            /*
             | Klien WSRS vendor (VendorWsrsClient): modul memanggil Ambil & Status Antrean Farmasi milik vendor.
             | Auth: GET {base_url}{path_auth} header x-username & x-password → response.token, dipakai di header x-token.
             | Token di-cache sampai exp JWT dikurangi 1 menit (tanpa exp: 'token_cache_menit_tanpa_exp').
             */
            'username' => env('ANTROL_VENDOR_USERNAME'),
            'password' => env('ANTROL_VENDOR_PASSWORD'),
            'path_auth' => env('ANTROL_VENDOR_PATH_AUTH', '/jkn/auth'), // VERIFIKASI DENGAN VENDOR
            'path_ambil_farmasi' => env('ANTROL_VENDOR_PATH_AMBIL_FARMASI', '/jkn/ambil-antrean-farmasi'), // VERIFIKASI DENGAN VENDOR
            'path_status_farmasi' => env('ANTROL_VENDOR_PATH_STATUS_FARMASI', '/jkn/status-antrean-farmasi'), // VERIFIKASI DENGAN VENDOR
            'wsrs_timeout' => (int) env('ANTROL_VENDOR_WSRS_TIMEOUT', 10), // detik
            'token_cache_menit_tanpa_exp' => (int) env('ANTROL_VENDOR_TOKEN_CACHE_TANPA_EXP', 5),
        ],
    ],

    // Sumber nomor antrean farmasi pada "Buat antrean farmasi" (onsite):
    //   vendor : nomor diambil dari Ambil Antrean Farmasi SIMRS vendor (satu urutan dengan Mobile JKN).
    //   lokal  : nomor urut tabel antrean farmasi modul ini.
    'nomor_farmasi_dari' => env('ANTROL_NOMOR_FARMASI_DARI', 'vendor'),

    /*
    |--------------------------------------------------------------------------
    | Sumber data antrean
    |--------------------------------------------------------------------------
    | 'lokal' : memakai tabel data uji di modul ini (untuk pengujian).
    | 'bpjs'  : antrean farmasi diperiksa ke BPJS (Antrean Per Kode Booking & List Waktu Task ID)
    |           dan data resep yang dikirim SIMRS vendor lewat POST /jkn/internal/resep.
    |           Modul tidak terhubung langsung ke database SIMRS vendor.
    */
    'sumber_data' => env('ANTROL_SUMBER_DATA', 'lokal'),

    // Mode 'bpjs': lama cache hasil pemeriksaan BPJS per kode booking (menit). Hanya hasil yang lolos
    // (ditemukan, hari ini, tidak batal, sudah task ID 5) yang di-cache.
    'cek_bpjs_cache_menit' => (int) env('ANTROL_CEK_BPJS_CACHE_MENIT', 2),

    // Kirim Update Waktu task ID 6 saat Panggil dan task ID 7 saat Selesai di halaman Antrian Farmasi.
    // VERIFIKASI DENGAN KATALOG: arti task 6 ("akhir tunggu farmasi / mulai layan farmasi, obat mulai dibuat")
    // dan task 7 ("obat selesai dibuat") terhadap tombol Panggil & Selesai.
    'kirim_task_farmasi' => (bool) env('ANTROL_KIRIM_TASK_FARMASI', true),

    /*
    |--------------------------------------------------------------------------
    | API internal untuk SIMRS vendor (/jkn/internal/*)
    |--------------------------------------------------------------------------
    | api_key       : dicocokkan dengan header x-api-key. Jika kosong, semua request ditolak.
    | ip_allowlist  : daftar IP dipisah koma; kosong = tidak dibatasi (hanya untuk pengujian).
    | per_menit     : batas request per menit per IP.
    */
    'internal' => [
        'api_key' => env('ANTROL_INTERNAL_API_KEY'),
        'ip_allowlist' => env('ANTROL_INTERNAL_IP_ALLOWLIST', ''),
        'per_menit' => (int) env('ANTROL_INTERNAL_PER_MENIT', 120),
    ],

    // Pesan yang tampil di pendaftaran jika poli tujuan termasuk poli fingerprint.
    'pesan_fingerprint' => env(
        'ANTROL_PESAN_FINGERPRINT',
        'Poli yang Anda tuju mewajibkan validasi sidik jari (fingerprint). Silakan lakukan perekaman/validasi sidik jari di loket pendaftaran sebelum dilayani.'
    ),

    // Lama cache referensi poli fingerprint (menit).
    'cache_poli_fp_minutes' => (int) env('ANTROL_CACHE_POLI_FP', 60),
];
