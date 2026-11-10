{{-- Keterangan sumber jadwal. $sumberJadwal: hasil AntreanRepository::ringkasanSumberJadwal(). --}}
<small class="redup" style="display:block;margin-bottom:4px">
  Sumber jadwal:
  @if ($sumberJadwal['sumber'] === 'bpjs')
    <b>hasil sinkron BPJS</b>{{ $sumberJadwal['terakhir_sinkron'] ? ' (terakhir '.$sumberJadwal['terakhir_sinkron'].')' : '' }}.
  @else
    <b>data fiktif</b>.
    Untuk memakai jadwal HFIS, jalankan <a href="{{ route('antrol.monitoring') }}#sinkron-jadwal">Sinkron jadwal dokter (HFIS)</a>.
  @endif
</small>
