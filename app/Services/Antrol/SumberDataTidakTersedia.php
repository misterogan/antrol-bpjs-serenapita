<?php

namespace App\Services\Antrol;

use RuntimeException;

/**
 * Sumber data antrean (BPJS) tidak dapat diperiksa: gagal terhubung, timeout, atau jawaban tidak dikenali.
 * Pengambilan antrean farmasi selalu DITOLAK dalam kondisi ini; pesannya ditampilkan apa adanya.
 */
class SumberDataTidakTersedia extends RuntimeException {}
