{{-- Sinkron jadwal dokter dari HFIS (dipakai pilihan jadwal Monitoring, item UAT 7). Bukan alat simulasi. --}}
<section class="panel" id="sinkron-jadwal">
  <h2>Sinkron jadwal dokter (HFIS)</h2>
  <p class="ket">Mengambil Referensi Poli, lalu Referensi Jadwal Dokter tiap poli untuk tanggal yang dipilih, dan menyimpannya sebagai jadwal hari tersebut (sumber <b>bpjs</b>). Jika sudah ada jadwal hasil sinkron, pilihan poli &amp; dokter di Pendaftaran, Poli, dan Monitoring hanya memakai jadwal hasil sinkron.</p>
  <p style="margin:0 0 6px">
    Sumber jadwal saat ini:
    @if ($sumberJadwal['sumber'] === 'bpjs')
      <span class="lencana ok">hasil sinkron BPJS</span>
    @else
      <span class="lencana">data fiktif</span>
    @endif
    <small class="redup">{{ $sumberJadwal['jumlah_bpjs'] }} jadwal hasil sinkron{{ $sumberJadwal['terakhir_sinkron'] ? ' (terakhir '.$sumberJadwal['terakhir_sinkron'].')' : '' }}, {{ $sumberJadwal['jumlah_fiktif'] }} jadwal fiktif.</small>
  </p>
  @php $modeAwal = old('mode', $poliBerjadwal ? 'berjadwal' : 'semua'); @endphp
  <form method="post" action="{{ route('antrol.monitoring.sinkron-jadwal') }}" data-pesan-memuat="Sinkron jadwal dokter dari BPJS, bisa memakan waktu beberapa menit..."
        onsubmit="if (this.ganti_fiktif && this.ganti_fiktif.checked && !confirm('Semua jadwal fiktif akan dihapus setelah sinkron berhasil. Lanjutkan?')) { return false; }">@csrf
    <div style="display:flex;gap:18px;align-items:flex-end;flex-wrap:wrap">
      <div><label for="sj-tgl">Tanggal jadwal</label><input id="sj-tgl" name="tanggal" type="date" value="{{ old('tanggal', $sinkron['tanggal'] ?? now()->toDateString()) }}" required style="width:auto"></div>
      <fieldset style="border:0;padding:0;margin:0">
        <legend style="font-size:12.5px;color:var(--redup);margin-bottom:3px">Poli yang disinkron</legend>
        <label style="margin:0;color:var(--tinta)">
          <input type="radio" name="mode" value="berjadwal" @checked($modeAwal === 'berjadwal') @disabled(! $poliBerjadwal)>
          Hanya poli yang pernah punya jadwal ({{ count($poliBerjadwal) }} poli)
        </label>
        <label style="margin:2px 0 0;color:var(--tinta)">
          <input type="radio" name="mode" value="semua" @checked($modeAwal === 'semua' || ! $poliBerjadwal)>
          Semua poli (Referensi Poli lengkap)
        </label>
      </fieldset>
      @if (\App\Services\Antrol\PelayananService::alatSimulasi())
        <label style="margin:0 0 4px;color:var(--tinta)"><input type="checkbox" name="ganti_fiktif" value="1"> Ganti data fiktif</label>
      @endif
      <button type="submit" style="margin:0">Sinkron jadwal dari BPJS</button>
    </div>
  </form>
  <small class="redup" style="display:block;margin-top:6px">
    Jeda {{ $jedaSinkronMs }} ms antar-panggilan. "Semua poli" memanggil Referensi Jadwal Dokter untuk setiap poli BPJS (ratusan panggilan, beberapa menit).
    Poli yang hanya praktek di hari lain baru masuk daftar setelah "Semua poli" dijalankan untuk tanggal pada hari tersebut.
  </small>
  @if ($poliBerjadwal)
    <details style="margin-top:6px"><summary style="cursor:pointer;font-size:13px;color:var(--redup)">Lihat poli yang pernah punya jadwal</summary>
      <small>@foreach ($poliBerjadwal as $kode => $nama){{ $kode }} – {{ $nama }}@if (! $loop->last), @endif @endforeach</small>
    </details>
  @endif

  @if ($sinkron)
    <h3>Hasil sinkron tanggal {{ \Illuminate\Support\Carbon::parse($sinkron['tanggal'])->format('d-m-Y') }}
      <small class="redup">({{ $sinkron['mode'] === 'berjadwal' ? 'poli yang pernah punya jadwal' : 'semua poli' }})</small></h3>
    <div class="statistik">
      <div><span>Poli diproses</span><b>{{ $sinkron['jumlah_poli'] - $sinkron['tidak_diproses'] }} / {{ $sinkron['jumlah_poli'] }}</b></div>
      <div class="utama"><span>Berhasil</span><b>{{ $sinkron['poli_berhasil'] }}</b></div>
      <div><span>Tidak ada jadwal</span><b>{{ count($sinkron['tanpa_jadwal']) }}</b></div>
      <div><span>Gagal</span><b>{{ count($sinkron['gagal']) }}</b></div>
      <div><span>Jadwal tersimpan</span><b>{{ $sinkron['jumlah_jadwal'] }}</b></div>
    </div>
    @if ($sinkron['dihentikan'])
      <div class="peringatan" role="alert">Sinkron dihentikan karena koneksi ke BPJS gagal berturut-turut. {{ $sinkron['tidak_diproses'] }} poli tidak diproses; periksa koneksi lalu sinkron ulang.</div>
    @endif
    @if ($sinkron['jumlah_libur'] || $sinkron['dilewati'])
      <small class="redup">{{ $sinkron['jumlah_libur'] }} jadwal bertanda libur (disimpan, tidak muncul di pendaftaran). {{ $sinkron['dilewati'] }} baris dilewati karena tanpa kode dokter atau jam praktek.</small>
    @endif
    @if ($sinkron['gagal'])
      @php $gagalKoneksi = collect($sinkron['gagal'])->where('jenis', 'koneksi')->count(); @endphp
      <label>Poli yang gagal diambil
        <small>({{ $gagalKoneksi }} gagal koneksi, {{ count($sinkron['gagal']) - $gagalKoneksi }} ditolak BPJS)</small></label>
      <div class="gulir gulir-tinggi"><table>
        <thead><tr><th>Kode poli</th><th>Nama poli</th><th>Jenis</th><th>Pesan</th></tr></thead>
        <tbody>
        @foreach ($sinkron['gagal'] as $g)
          <tr>
            <td>{{ $g['kodepoli'] }}</td><td>{{ $g['namapoli'] }}</td>
            <td>@if ($g['jenis'] === 'koneksi')<span class="lencana tidak">gagal koneksi</span>@else<span class="lencana">ditolak BPJS</span>@endif</td>
            <td>{{ $g['pesan'] }}</td>
          </tr>
        @endforeach
        </tbody>
      </table></div>
      <small class="redup">Jadwal lama poli tersebut tidak diubah.</small>
    @endif
    @if ($sinkron['tanpa_jadwal'])
      <details style="margin-top:8px"><summary style="cursor:pointer;font-size:13px">Poli tanpa jadwal pada tanggal ini ({{ count($sinkron['tanpa_jadwal']) }})</summary>
        <small class="redup">@foreach ($sinkron['tanpa_jadwal'] as $t){{ $t['kodepoli'] }} – {{ $t['namapoli'] }}@if (! $loop->last), @endif @endforeach</small>
      </details>
    @endif
    <p style="margin:8px 0 0"><small>Request &amp; response tiap panggilan ada di <a href="{{ route('antrol.log', ['arah' => 'wsbpjs']) }}">Log bridging</a>.</small></p>
  @endif
</section>
