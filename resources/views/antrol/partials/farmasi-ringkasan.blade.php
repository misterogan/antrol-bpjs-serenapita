{{-- Ringkasan antrean farmasi hari ini. Angka sama dengan WSRS Status Antrean Farmasi (antrean batal tidak dihitung). --}}
<div class="utama"><span>Sedang dipanggil</span><b>{{ $statistik['dipanggil'] ?: '-' }}</b></div>
<div><span>Total antrean</span><b>{{ $statistik['total'] }}</b></div>
<div><span>Sisa (menunggu)</span><b>{{ $statistik['sisa'] }}</b></div>
<div><span>Selesai</span><b>{{ $statistik['selesai'] }}</b></div>
<div><span>Terkirim ke BPJS</span><b>{{ $terkirim }}<small class="redup" style="font-size:14px"> / {{ $perluKirim }}</small></b>
  @if ($dariMobileJkn)<small class="redup">{{ $dariMobileJkn }} dari Mobile JKN tidak dihitung</small>@endif</div>
