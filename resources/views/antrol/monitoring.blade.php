@extends('antrol.layout')
@section('judul', 'Monitoring')
@php
  $hariIni = now()->dayOfWeekIso;
  $hariDipilih = (int) old('hari', $hariIni);
  $pilihanJadwal = collect($jadwal)->unique(fn ($j) => $j['hari'] . '|' . $j['kodepoli'] . '|' . $j['kodedokter'] . '|' . $j['jampraktek']);
@endphp
@section('isi')
<div class="kepala">
  <div>
    <h1>Monitoring antrean</h1>
    <p class="sub">Antrean yang belum dilayani menurut data BPJS. Pilihan poli, dokter, dan jam praktek diambil dari jadwal praktek yang sudah ada.</p>
  </div>
</div>

<div class="dua-kolom">
  <section class="panel">
    <h2>Antrean belum dilayani</h2>
    <p class="ket">Seluruh antrean RS yang belum dilayani.</p>
    <form method="post" action="{{ route('antrol.monitoring.belum-dilayani') }}" data-boleh-ulang>@csrf
      <button type="submit">Tampilkan semua</button>
    </form>
  </section>

  <section class="panel">
    <h2>Belum dilayani per poli, dokter, hari, dan jam praktek</h2>
    @include('antrol.partials.sumber-jadwal')
    <form method="post" action="{{ route('antrol.monitoring.belum-dilayani-poli') }}" data-boleh-ulang>@csrf
      <div class="baris" style="grid-template-columns:180px 1fr">
        <div>
          <label for="mn-hari">Hari</label>
          <select id="mn-hari" name="hari" required>
            @foreach ($hari as $n => $nama)
              <option value="{{ $n }}" @selected($hariDipilih === $n)>{{ $n }}. {{ $nama }}{{ $n === $hariIni ? ' (hari ini)' : '' }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label for="mn-jadwal">Poli · dokter · jam praktek</label>
          <select id="mn-jadwal" name="jadwal">
            <option value="">Pilih jadwal praktek</option>
            @foreach ($pilihanJadwal as $j)
              @php $nilai = $j['kodepoli'] . '|' . $j['kodedokter'] . '|' . $j['jampraktek']; @endphp
              <option value="{{ $nilai }}" data-hari="{{ $j['hari'] }}" @selected(old('jadwal') === $nilai && $hariDipilih === $j['hari'])>
                {{ $j['namapoli'] }} ({{ $j['kodepoli'] }}) · {{ $j['namadokter'] }} ({{ $j['kodedokter'] }}) · {{ $j['jampraktek'] }}
              </option>
            @endforeach
          </select>
        </div>
      </div>
      <details style="margin-top:8px" @if (old('kodepoli')) open @endif>
        <summary style="cursor:pointer;color:var(--redup);font-size:13px">Isi kode manual (jika jadwal belum ada di daftar)</summary>
        <div class="baris-3">
          <div><label for="mn-poli">Kode poli</label><input id="mn-poli" name="kodepoli" maxlength="10" value="{{ old('kodepoli') }}"></div>
          <div><label for="mn-dokter">Kode dokter</label><input id="mn-dokter" name="kodedokter" maxlength="15" value="{{ old('kodedokter') }}"></div>
          <div><label for="mn-jam">Jam praktek</label><input id="mn-jam" name="jampraktek" maxlength="20" placeholder="08:00-12:00" value="{{ old('jampraktek') }}"></div>
        </div>
      </details>
      <button type="submit">Tampilkan</button>
    </form>
  </section>
</div>

@if ($monitor)
  @php $kode = $monitor['hasil']['metadata']['code'] ?? null; $sukses = \App\Services\Antrol\WsbpjsService::berhasil($monitor['hasil']); @endphp
  <section class="panel hasil {{ $sukses ? '' : 'gagal' }}" style="margin-top:14px">
    <h2>{{ $monitor['judul'] }} <span class="kode">code {{ $kode ?? '-' }}</span></h2>
    <small class="redup">Pukul {{ $monitor['waktu'] }} · {{ $monitor['hasil']['metadata']['message'] ?? ($monitor['hasil']['error'] ?? '') }}</small>
    @if ($tabel)
      <p style="margin:8px 0 4px"><b>{{ count($tabel['baris']) }}</b> antrean belum dilayani</p>
      <div class="gulir gulir-tinggi">
        <table>
          <thead><tr><th>#</th>@foreach ($tabel['kolom'] as $k)<th>{{ $k }}</th>@endforeach</tr></thead>
          <tbody>
            @foreach ($tabel['baris'] as $i => $b)
              <tr><td>{{ $i + 1 }}</td>@foreach ($tabel['kolom'] as $k)<td>{{ $b[$k] ?? '' }}</td>@endforeach</tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @elseif ($sukses)
      <p class="redup" style="margin:8px 0 0">Tidak ada data dalam bentuk daftar. Lihat response lengkap di bawah.</p>
    @endif
    <details style="margin-top:10px">
      <summary style="cursor:pointer;color:var(--redup);font-size:13px">Lihat request &amp; response lengkap</summary>
      @include('antrol.partials.hasil', ['hasil' => $monitor['hasil'], 'judul' => $monitor['judul']])
    </details>
  </section>
@endif

<div style="margin-top:14px">
@include('antrol.partials.panel-sinkron-jadwal')
</div>
@endsection

@push('skrip')
<script>
(function () {
  var hari = document.getElementById('mn-hari');
  var jadwal = document.getElementById('mn-jadwal');
  if (!hari || !jadwal) return;
  // Tampilkan hanya jadwal pada hari yang dipilih.
  function saring() {
    Array.prototype.forEach.call(jadwal.options, function (o) {
      if (!o.value) return;
      var cocok = o.dataset.hari === hari.value;
      o.hidden = !cocok; o.disabled = !cocok;
    });
    var pilih = jadwal.options[jadwal.selectedIndex];
    if (pilih && pilih.disabled) jadwal.value = '';
  }
  hari.addEventListener('change', saring);
  saring();
})();
</script>
@endpush
