<h1>Antrian Pendaftaran Onsite BPJS</h1>
<nav class="tab" aria-label="Antrian Pendaftaran Onsite BPJS" style="margin-top:10px">
  <a href="{{ route('antrol.pendaftaran.poli-fp') }}" class="{{ request()->routeIs('antrol.pendaftaran.poli-fp*') ? 'aktif' : '' }}">Cek Poli Fingerprint</a>
  <a href="{{ route('antrol.pendaftaran.peserta-fp') }}" class="{{ request()->routeIs('antrol.pendaftaran.peserta-fp*') ? 'aktif' : '' }}">Cek Peserta Fingerprint</a>
  @if (\App\Services\Antrol\PelayananService::alatSimulasi())
    <a href="{{ route('antrol.pendaftaran') }}" class="{{ request()->routeIs('antrol.pendaftaran', 'antrol.pendaftaran.periksa', 'antrol.pendaftaran.simpan', 'antrol.pendaftaran.pasien') ? 'aktif' : '' }}">Pendaftaran (simulasi)</a>
  @endif
</nav>
