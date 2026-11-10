@extends('antrol.layout')
@section('judul', 'Poli')
@php
  $simulasi = \App\Services\Antrol\PelayananService::modeSimulasi();
  $langkah = [
      'terdaftar' => ['checkin', 'Check in'],
      'checkin'   => ['panggil', 'Panggil'],
      'dipanggil' => ['mulai', 'Mulai pelayanan'],
  ];
  $bisaBatal = ['terdaftar', 'checkin', 'dipanggil', 'dilayani'];
@endphp
@section('isi')
@include('antrol.partials.tab-uji')
<div class="kepala">
  <div>
    <h1>Antrean poli hari ini</h1>
    <p class="sub">Urutan pelayanan: Check in, Panggil, Mulai pelayanan, Selesai pelayanan. Saat selesai, dokter wajib memilih resep (menandai task ID 5).
      @if ($simulasi) <b>Mode simulasi:</b> check in, mulai, dan selesai juga mengirim Update Waktu task ID 3, 4, 5 ke BPJS.@endif</p>
  </div>
  <form method="get" action="{{ route('antrol.poli') }}" class="filter">
    <div>
      <label for="fl-poli">Poli</label>
      <select id="fl-poli" name="kodepoli" onchange="this.form.kodedokter.value='';this.form.submit()">
        <option value="">Semua poli</option>
        @foreach ($poliList as $p)
          <option value="{{ $p['kodepoli'] }}" @selected($kodepoli === $p['kodepoli'])>{{ $p['namapoli'] }} ({{ $p['kodepoli'] }})</option>
        @endforeach
      </select>
    </div>
    <div>
      <label for="fl-dokter">Dokter</label>
      <select id="fl-dokter" name="kodedokter" onchange="this.form.submit()">
        <option value="">Semua dokter</option>
        @foreach ($dokterList as $d)
          @if (! $kodepoli || $d['kodepoli'] === $kodepoli)
            <option value="{{ $d['kodedokter'] }}" @selected($kodedokter === $d['kodedokter'])>{{ $d['namadokter'] }}</option>
          @endif
        @endforeach
      </select>
    </div>
    <noscript><button type="submit" class="garis">Tampilkan</button></noscript>
  </form>
</div>

<div class="statistik">
  <div class="utama"><b>{{ count($antrean) }}</b><span>Total antrean</span></div>
  <div><b>{{ ($jumlah['terdaftar'] ?? 0) + ($jumlah['checkin'] ?? 0) }}</b><span>Menunggu (terdaftar / check in)</span></div>
  <div><b>{{ ($jumlah['dipanggil'] ?? 0) + ($jumlah['dilayani'] ?? 0) }}</b><span>Dipanggil / dilayani</span></div>
  <div><b>{{ $jumlah['selesai'] ?? 0 }}</b><span>Selesai</span></div>
  <div><b>{{ $jumlah['batal'] ?? 0 }}</b><span>Batal</span></div>
</div>

<section class="panel">
  <div class="gulir">
    <table>
      <thead>
        <tr>
          <th>No.</th><th>Pasien</th><th>Poli · dokter · jam</th><th>Status</th><th>Waktu</th>
          @if ($simulasi)<th>BPJS</th>@endif
          <th style="min-width:330px">Aksi</th>
        </tr>
      </thead>
      <tbody>
      @forelse ($antrean as $a)
        @php $kb = $a['kodebooking']; @endphp
        <tr class="{{ $a['status'] === 'batal' ? 'redup' : '' }}" id="kb-{{ $kb }}">
          <td><span class="no-antrean">{{ $a['nomorantrean'] ?? '-' }}</span><br><small class="redup">{{ $kb }}</small></td>
          <td>{{ $a['nama'] ?? '-' }} @if ($a['wajib_fp'])<span class="lencana fp" title="Poli fingerprint">FP</span>@endif
            <br><small class="redup">RM {{ $a['norm'] ?? '-' }} · {{ $a['jenispasien'] }} {{ $a['nomorkartu'] }}</small></td>
          <td>{{ $a['namapoli'] ?? $a['kodepoli'] ?? '-' }}<br><small class="redup">{{ $a['namadokter'] ?? '-' }} · {{ $a['jampraktek'] ?? '-' }}</small></td>
          <td>@include('antrol.partials.lencana-status', ['status' => $a['status']])
            @if ($a['status'] === 'selesai')<br><small>Resep: <b>{{ $a['jenisresep'] ?? '-' }}</b></small>@endif</td>
          <td><small class="redup">
            @if ($a['checkin_at'])Check in {{ $a['checkin_at'] }}<br>@endif
            @if ($a['dipanggil_at'])Dipanggil {{ $a['dipanggil_at'] }}<br>@endif
            @if ($a['mulai_at'])Mulai {{ $a['mulai_at'] }}<br>@endif
            @if ($a['selesai_at'])Selesai {{ $a['selesai_at'] }}@endif
            @if (! $a['checkin_at'] && ! $a['selesai_at'])-@endif
          </small></td>
          @if ($simulasi)
            <td>
              @if ($a['dikirim_bpjs'])
                <span class="lencana ok">terkirim</span>
              @else
                <form method="post" action="{{ route('antrol.poli.kirim-bpjs', $kb) }}">@csrf
                  <button class="kecil garis" title="Kirim ulang Tambah Antrean">Kirim ulang</button>
                </form>
              @endif
            </td>
          @endif
          <td>
            <div class="aksi">
              @if (isset($langkah[$a['status']]))
                @php [$aksi, $label] = $langkah[$a['status']]; @endphp
                <form method="post" action="{{ route('antrol.poli.aksi', [$kb, $aksi]) }}">@csrf
                  <button class="kecil">{{ $label }}</button>
                </form>
              @endif
              @if ($a['status'] === 'dipanggil')
                <form method="post" action="{{ route('antrol.poli.aksi', [$kb, 'panggil']) }}">@csrf
                  <button class="kecil garis">Panggil ulang</button>
                </form>
              @endif
              @if ($a['status'] === 'dilayani')
                <form method="post" action="{{ route('antrol.poli.aksi', [$kb, 'selesai']) }}">@csrf
                  <label class="redup" for="resep-{{ $kb }}" style="margin:0">Resep</label>
                  <select id="resep-{{ $kb }}" name="jenisresep" required>
                    <option value="">Pilih resep</option>
                    @foreach ($jenisResep as $jr)
                      <option value="{{ $jr }}">{{ $jr === 'Tidak ada' ? 'Tidak ada resep' : $jr }}</option>
                    @endforeach
                  </select>
                  <button class="kecil">Selesai pelayanan</button>
                </form>
              @endif
              @if (config('antrol.fitur_farmasi') && $a['status'] === 'selesai' && in_array($a['jenisresep'], ['Racikan', 'Non Racikan'], true))
                <a href="{{ route('antrol.farmasi') }}" class="tombol kecil garis">Ke farmasi</a>
              @endif
              @if (in_array($a['status'], $bisaBatal, true))
                <form method="post" action="{{ route('antrol.poli.aksi', [$kb, 'batal']) }}" data-konfirmasi="Batalkan antrean {{ $a['nomorantrean'] ?? $kb }} atas nama {{ $a['nama'] }}?">@csrf
                  <button class="kecil bahaya">Batalkan</button>
                </form>
              @endif
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="7"><small class="redup">Belum ada antrean poli hari ini. Daftarkan pasien di halaman <a href="{{ route('antrol.pendaftaran') }}">Pendaftaran</a>.</small></td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</section>
@endsection
