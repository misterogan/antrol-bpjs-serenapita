<nav class="tab" aria-label="Alat uji">
  <a href="{{ route('antrol.alat-uji') }}" class="{{ request()->routeIs('antrol.alat-uji') ? 'aktif' : '' }}">Alat Uji</a>
  @if (\App\Services\Antrol\PelayananService::alatSimulasi())
  <a href="{{ route('antrol.poli') }}" class="{{ request()->routeIs('antrol.poli*') ? 'aktif' : '' }}">Poli (simulasi)</a>
  <a href="{{ route('antrol.data-uji') }}" class="{{ request()->routeIs('antrol.data-uji') ? 'aktif' : '' }}">Data uji &amp; sinkron jadwal</a>
  @endif
  <a href="{{ route('antrol.wsbpjs') }}" class="{{ request()->routeIs('antrol.wsbpjs') ? 'aktif' : '' }}">Uji service</a>
</nav>
