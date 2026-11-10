@extends('antrol.layout')
@section('judul', 'Daftar Antrian')
@push('gaya')
<style>
.daftar-antrian{max-width:1080px;margin:6px auto 0}
.daftar-nav{display:flex;justify-content:space-between;align-items:center;min-height:22px;margin:0 0 12px}
.daftar-nav a{display:flex;align-items:center;gap:5px;color:var(--toska);font-size:13px;font-weight:500;letter-spacing:.3px;text-transform:uppercase;text-decoration:none}
.daftar-nav a:hover{color:var(--toska-tua)}
.daftar-nav .kanan{margin-left:auto}
.kartu-antrian-deret{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.kartu-antrian{background:#fff;border:1px solid #E1E1E1;min-height:392px;display:flex;flex-direction:column;align-items:center;justify-content:space-between;padding:26px 18px 30px;text-align:center}
.kartu-antrian h2{margin:0;font-size:14px;font-weight:600;color:#555;text-transform:uppercase;line-height:1.6;letter-spacing:.2px}
.kartu-antrian .tombol{margin:0;padding:8px 13px;min-width:108px}
</style>
@endpush
@section('isi')
<div class="daftar-antrian">
  <h1 style="position:absolute;left:-9999px">Daftar Antrian</h1>
  <nav class="daftar-nav" aria-label="Halaman daftar antrian">
    @if ($halaman > 1)
      <a href="{{ route('antrol.beranda', ['hal' => $halaman - 1]) }}">@include('antrol.partials.ikon', ['nama' => 'kiri', 'ukuran' => 15]) Daftar sebelumnya</a>
    @endif
    @if ($halaman < $jumlahHalaman)
      <a class="kanan" href="{{ route('antrol.beranda', ['hal' => $halaman + 1]) }}">Daftar selanjutnya @include('antrol.partials.ikon', ['nama' => 'kanan', 'ukuran' => 15])</a>
    @endif
  </nav>

  <div class="kartu-antrian-deret">
    @foreach ($kartu as $k)
      <section class="kartu-antrian">
        @include('antrol.partials.ikon-kartu', ['nama' => $k['ikon']])
        <h2>{{ $k['judul'][0] }}<br>{{ $k['judul'][1] }}</h2>
        <a class="tombol" href="{{ $k['tujuan'] }}">{{ $k['tombol'] }}</a>
      </section>
    @endforeach
  </div>
</div>
@endsection
