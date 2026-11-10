<?php

namespace App\Http\Controllers\Antrol;

use App\Http\Controllers\Controller;
use App\Models\Antrol\AntrolLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Log bridging WSBPJS & WSRS, bukti request/response untuk UAT. */
class LogController extends Controller
{
    public function index(Request $r): View
    {
        $arah = in_array($r->query('arah'), ['wsbpjs', 'wsrs', 'vendor', 'internal'], true) ? $r->query('arah') : null;
        $kata = trim((string) $r->query('q', ''));
        $tanggal = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $r->query('tanggal')) ? $r->query('tanggal') : null;

        $log = AntrolLog::query()
            ->when($arah, fn ($q) => $q->where('arah', $arah))
            ->when($kata !== '', fn ($q) => $q->where('endpoint', 'like', '%'.addcslashes($kata, '%_\\').'%'))
            ->when($tanggal, fn ($q) => $q->whereDate('created_at', $tanggal))
            ->orderByDesc('id')
            ->simplePaginate(25)
            ->withQueryString();

        return view('antrol.log', [
            'log' => $log,
            'arah' => $arah,
            'kata' => $kata,
            'tanggal' => $tanggal,
        ]);
    }
}
