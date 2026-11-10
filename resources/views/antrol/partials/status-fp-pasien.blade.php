{{--
  Status Referensi Pasien Fingerprint.
  $fp: ['jenis', 'nomor', 'hasil' (hasil AntrolClient), 'waktu'] atau null.
  Isi response ditampilkan apa adanya (key-value) karena arti field mengikuti katalog BPJS.
--}}
@php
  $hasilFp = $fp['hasil'] ?? null;
  $kodeFp = $hasilFp['metadata']['code'] ?? null;
  $suksesFp = \App\Services\Antrol\WsbpjsService::berhasil($hasilFp);
  $isiFp = $hasilFp['response'] ?? null;
  $barisFp = [];
  if (is_array($isiFp)) {
      array_walk_recursive($isiFp, function ($v, $k) use (&$barisFp) {
          if (! is_array($v) && $k !== '_gagal_dekrip') { $barisFp[$k] = is_bool($v) ? ($v ? 'true' : 'false') : (string) $v; }
      });
  }
@endphp
<div class="kartu-pasien" style="margin-top:10px">
  <div style="display:flex;justify-content:space-between;gap:8px;align-items:center">
    <b style="font-size:13.5px">Referensi Pasien Fingerprint</b>
    @if (! $fp)
      <span class="lencana">tidak dicek</span>
    @elseif ($hasilFp['error'] ?? null)
      <span class="lencana tidak">gagal terhubung</span>
    @elseif ($suksesFp)
      <span class="lencana ok">code {{ $kodeFp }}</span>
    @else
      <span class="lencana">code {{ $kodeFp ?? '-' }}</span>
    @endif
  </div>
  @if (! $fp)
    <small class="redup">Pasien belum memiliki nomor kartu atau NIK.</small>
  @else
    <small class="redup">Dicek dengan {{ $fp['jenis'] === 'noka' ? 'nomor kartu' : 'NIK' }} {{ $fp['nomor'] }} pukul {{ $fp['waktu'] ?? '-' }}.</small>
    <div style="margin-top:4px">{{ $hasilFp['metadata']['message'] ?? ($hasilFp['error'] ?? '') }}</div>
    @if ($barisFp)
      <dl>
        @foreach ($barisFp as $k => $v)<dt>{{ $k }}</dt><dd>{{ $v }}</dd>@endforeach
      </dl>
    @endif
  @endif
</div>
