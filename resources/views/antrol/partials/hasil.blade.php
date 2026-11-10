@php
  $kode = $hasil['metadata']['code'] ?? ($hasil['lokal']['metadata']['code'] ?? null);
  $ok = $kode !== null && in_array((int) $kode, array_map('intval', (array) config('antrol.kode_sukses', [200])), true);
@endphp
<section class="panel hasil {{ $ok ? '' : 'gagal' }}">
  <h2>{{ $judul }} <span class="kode">code {{ $kode ?? '-' }}</span></h2>
  <small class="redup">Dijalankan {{ now()->format('d-m-Y H:i:s') }}</small>

  @if (isset($hasil['url']))
    <label>Request</label>
<pre>{{ $hasil['method'] }} {{ $hasil['url'] }}
{{ json_encode($hasil['headers'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}
@if ($hasil['body'])
{{ json_encode($hasil['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}
@endif</pre>
    <label>Metadata</label>
    <pre>{{ json_encode($hasil['metadata'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    @php
      $asli = is_string($hasil['raw'] ?? null) ? (json_decode($hasil['raw'], true)['response'] ?? null) : null;
      $batasAsli = 500;
    @endphp
    <label>Response asli dari BPJS (terenkripsi)</label>
    @if (is_string($asli) && $asli !== '')
<pre style="white-space:pre-wrap;word-break:break-all">{{ mb_strlen($asli) > $batasAsli ? mb_substr($asli, 0, $batasAsli).'...' : $asli }}</pre>
      <small class="redup">Panjang asli {{ number_format(mb_strlen($asli), 0, ',', '.') }} karakter{{ mb_strlen($asli) > $batasAsli ? ', ditampilkan '.$batasAsli.' karakter pertama' : '' }}.</small>
    @else
      <small class="redup">Tidak ada field "response" terenkripsi pada body yang diterima.</small>
    @endif
    @if (is_string($asli) && $asli !== '')
      <label>Response (sudah didekripsi &amp; didekompresi)</label>
    @else
      <label>Response (diterima tanpa enkripsi dari BPJS)</label>
    @endif
    <pre>{{ json_encode($hasil['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
    @if ($hasil['error'])<div class="galat">{{ $hasil['error'] }}</div>@endif
  @else
    <pre>{{ json_encode($hasil, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
  @endif
</section>
