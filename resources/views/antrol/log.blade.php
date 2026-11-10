@extends('antrol.layout')
@section('judul', 'Log bridging')
@php
  $json = fn ($v) => json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@endphp
@section('isi')
<div class="kepala">
  <div>
    <h1>Log bridging</h1>
    <p class="sub">Setiap request ke WS BPJS dan setiap request yang masuk ke WSRS dicatat di sini. Header rahasia sudah disamarkan. Bisa dilampirkan sebagai bukti di form UAT.</p>
  </div>
</div>

<section class="panel">
  <form method="get" action="{{ route('antrol.log') }}" class="filter" style="margin-bottom:12px">
    <div>
      <label for="lg-arah">Arah</label>
      <select id="lg-arah" name="arah">
        <option value="">Semua</option>
        <option value="wsbpjs" @selected($arah === 'wsbpjs')>WSBPJS (RS → BPJS)</option>
        <option value="wsrs" @selected($arah === 'wsrs')>WSRS (BPJS → RS)</option>
        <option value="vendor" @selected($arah === 'vendor')>Validasi token SIMRS</option>
        <option value="internal" @selected($arah === 'internal')>Kiriman dari SIMRS</option>
      </select>
    </div>
    <div style="min-width:240px"><label for="lg-q">Endpoint memuat</label><input id="lg-q" name="q" value="{{ $kata }}" placeholder="mis. farmasi"></div>
    <div><label for="lg-tgl">Tanggal</label><input id="lg-tgl" type="date" name="tanggal" value="{{ $tanggal }}"></div>
    <button type="submit" data-boleh-ulang>Terapkan</button>
    @if ($arah || $kata !== '' || $tanggal)<a href="{{ route('antrol.log') }}">Hapus filter</a>@endif
  </form>

  <div class="gulir">
    <table>
      <thead><tr><th>Waktu</th><th>Arah</th><th>Endpoint</th><th>HTTP</th><th>Metadata</th><th></th></tr></thead>
      <tbody>
      @forelse ($log as $l)
        @php
          $res = is_array($l->response) ? $l->response : [];
          $kode = $res['metadata']['code'] ?? null;
          $sukses = \App\Services\Antrol\WsbpjsService::berhasil($res);
          $pesan = $res['metadata']['message'] ?? ($res['error'] ?? null);
          if ($l->arah === 'vendor') {
              // Validasi token SIMRS: tampilkan hasil klasifikasi, bukan kode sukses BPJS.
              $sukses = ($res['hasil'] ?? null) === 'sah';
              $pesan = 'token '.($res['hasil'] ?? '-').' · '.($res['alasan'] ?? $pesan ?? '');
          }
        @endphp
        <tr>
          <td style="white-space:nowrap">{{ $l->created_at->format('d-m-Y H:i:s') }}</td>
          <td><span class="lencana {{ ['wsrs' => 'checkin', 'vendor' => 'fp', 'simulator' => 'simulator', 'internal' => 'internal'][$l->arah] ?? 'dilayani' }}">{{ ['wsbpjs' => 'WSBPJS', 'wsrs' => 'WSRS', 'vendor' => 'VALIDASI TOKEN SIMRS', 'internal' => 'KIRIMAN DARI SIMRS', 'simulator' => 'SIMULATOR'][$l->arah] ?? strtoupper($l->arah) }}</span></td>
          <td style="word-break:break-all">{{ $l->endpoint }}</td>
          <td>{{ $l->http_status ?? '-' }}</td>
          <td><span class="lencana {{ $sukses ? 'ok' : 'tidak' }}">{{ $kode ?? '-' }}</span> <small>{{ \Illuminate\Support\Str::limit((string) $pesan, 70) }}</small></td>
          <td><button type="button" class="kecil garis" data-buka="log-{{ $l->id }}" aria-expanded="false" aria-controls="log-{{ $l->id }}">Detail</button></td>
        </tr>
        <tr id="log-{{ $l->id }}" hidden>
          <td colspan="6" style="background:#FAFBFA">
            @if ($l->arah === 'wsbpjs' && isset($res['url']))
              <div class="baris">
                <div>
                  <label>Request</label>
<pre>{{ $res['method'] ?? '' }} {{ $res['url'] }}
{{ $json($res['headers'] ?? []) }}
@if (! empty($res['body']))
{{ $json($res['body']) }}
@endif</pre>
                </div>
                <div>
                  <label>Response (metadata &amp; response yang sudah didekripsi)</label>
                  <pre>{{ $json(['metadata' => $res['metadata'] ?? null, 'response' => $res['response'] ?? null]) }}</pre>
                  @if (! empty($res['error']))<div class="galat">{{ $res['error'] }}</div>@endif
                </div>
              </div>
              @if (! empty($res['raw']))
                <details><summary style="cursor:pointer;color:var(--redup);font-size:13px">Response mentah (terenkripsi)</summary><pre>{{ $res['raw'] }}</pre></details>
              @endif
            @else
              <div class="baris">
                <div><label>Request</label><pre>{{ $json($l->request) }}</pre></div>
                <div><label>Response</label><pre>{{ $json($l->response) }}</pre></div>
              </div>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="6"><small class="redup">Belum ada log yang sesuai filter.</small></td></tr>
      @endforelse
      </tbody>
    </table>
  </div>

  @if ($log->hasPages())
    <nav class="aksi" style="margin-top:12px;justify-content:space-between" aria-label="Halaman log">
      @if ($log->onFirstPage())<span class="redup">‹ Lebih baru</span>@else<a class="tombol garis kecil" href="{{ $log->previousPageUrl() }}">‹ Lebih baru</a>@endif
      <span class="redup">Halaman {{ $log->currentPage() }}</span>
      @if ($log->hasMorePages())<a class="tombol garis kecil" href="{{ $log->nextPageUrl() }}">Lebih lama ›</a>@else<span class="redup">Lebih lama ›</span>@endif
    </nav>
  @endif
</section>
@endsection
