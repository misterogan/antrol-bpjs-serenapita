@extends('antrol.layout')
@section('judul', 'Pendaftaran')
@php
  $poliList = collect($jadwal)->unique('kodepoli')->values();
  $tglTampil = \Illuminate\Support\Carbon::parse($tanggal)->locale('id')->translatedFormat('l, d F Y');
  $simulasi = \App\Services\Antrol\PelayananService::modeSimulasi();
@endphp
@section('isi')
@include('antrol.partials.tab-pendaftaran')
<div class="kepala">
  <div>
    <h2 style="font-size:16px;font-weight:500;margin:0 0 2px">Pendaftaran rawat jalan (simulasi)</h2>
    <p class="sub">Pilih pasien, poli tujuan, dokter, dan jam praktek. Jika poli tujuan termasuk poli fingerprint, pesan ditampilkan sebelum pendaftaran disimpan.</p>
  </div>
  <form method="get" action="{{ route('antrol.pendaftaran') }}" class="filter">
    @if ($pasien)<input type="hidden" name="norm" value="{{ $pasien['norm'] }}">@endif
    @if (request('jenispasien'))<input type="hidden" name="jenispasien" value="{{ request('jenispasien') }}">@endif
    <div><label for="tgl">Tanggal periksa</label><input id="tgl" type="date" name="tanggal" value="{{ $tanggal }}" min="{{ now()->toDateString() }}" onchange="this.form.submit()"></div>
    <noscript><button type="submit" class="garis">Tampilkan</button></noscript>
  </form>
</div>

@if ($poliFpGagal)
  <div class="peringatan" role="alert">Referensi Poli Fingerprint dari BPJS gagal dibaca, sehingga pesan poli fingerprint mungkin tidak muncul. Sistem mencoba lagi dalam 1 menit; periksa juga halaman <a href="{{ route('antrol.log', ['arah' => 'wsbpjs']) }}">Log bridging</a>.</div>
@endif

@if ($terdaftar)
  <div class="tiket" role="status">
    <div><span>Kode booking</span><div class="besar">{{ $terdaftar['kodebooking'] }}</div></div>
    <div><span>Nomor antrean poli</span><div class="besar">{{ $terdaftar['nomorantrean'] }}</div></div>
    <div><span>Pasien</span><b>{{ $terdaftar['nama'] }}</b><span>No. RM {{ $terdaftar['norm'] }} · {{ $terdaftar['jenispasien'] }}</span></div>
    <div><span>Poli / dokter</span><b>{{ $terdaftar['namapoli'] }}</b><span>{{ $terdaftar['namadokter'] }} · {{ $terdaftar['jampraktek'] }}</span></div>
    @if ($terdaftar['wajib_fp'])<span class="lencana fp">Poli fingerprint</span>@endif
    <a class="tombol garis" style="margin-left:auto;margin-top:0" href="{{ route('antrol.poli', ['kodepoli' => $terdaftar['kodepoli']]) }}">Buka antrean poli</a>
  </div>
@endif

<div class="dua-kolom">
  {{-- ============ 1. PASIEN ============ --}}
  <section class="panel" aria-labelledby="judul-pasien">
    <h2 id="judul-pasien">1. Pasien</h2>
    @if ($pasien)
      <div class="kartu-pasien">
        <div style="display:flex;justify-content:space-between;gap:8px">
          <b>{{ $pasien['nama'] }}</b>
          <a href="{{ route('antrol.pendaftaran', ['tanggal' => $tanggal]) }}">Ganti pasien</a>
        </div>
        <dl>
          <dt>No. RM</dt><dd>{{ $pasien['norm'] }}</dd>
          <dt>Nomor kartu</dt><dd>{{ $pasien['nomorkartu'] ?: '-' }}</dd>
          <dt>NIK</dt><dd>{{ $pasien['nik'] ?: '-' }}</dd>
          <dt>No. HP</dt><dd>{{ $pasien['nohp'] ?: '-' }}</dd>
          @if ($pasien['tanggal_lahir'])<dt>Tanggal lahir</dt><dd>{{ \Illuminate\Support\Carbon::parse($pasien['tanggal_lahir'])->format('d-m-Y') }}</dd>@endif
        </dl>
      </div>
      @include('antrol.partials.status-fp-pasien', ['fp' => $fpPasien])
      @if ($fpPasien)
        <a class="tombol garis kecil" style="margin-top:8px" href="{{ route('antrol.pendaftaran', ['norm' => $pasien['norm'], 'tanggal' => $tanggal, 'cek' => 1, 'jenispasien' => request('jenispasien')]) }}">Cek ulang fingerprint</a>
      @endif
    @else
      <form method="get" action="{{ route('antrol.pendaftaran') }}">
        <input type="hidden" name="tanggal" value="{{ $tanggal }}">
        @if (request('jenispasien'))<input type="hidden" name="jenispasien" value="{{ request('jenispasien') }}">@endif
        <label for="q">Cari nomor kartu, NIK, No. RM, atau nama</label>
        <div style="display:flex;gap:8px">
          <input id="q" name="q" value="{{ $kata }}" autofocus autocomplete="off">
          <button type="submit" style="margin-top:0" data-boleh-ulang>Cari</button>
        </div>
      </form>

      @if ($kata !== '')
        <div class="gulir" style="margin-top:10px">
          <table>
            <thead><tr><th>No. RM</th><th>Nama</th><th>Nomor kartu / NIK</th><th></th></tr></thead>
            <tbody>
            @forelse ($hasilCari as $p)
              <tr>
                <td>{{ $p['norm'] }}</td>
                <td>{{ $p['nama'] }}</td>
                <td>{{ $p['nomorkartu'] ?: '-' }}<br><small class="redup">{{ $p['nik'] ?: '-' }}</small></td>
                <td><a class="tombol kecil" href="{{ route('antrol.pendaftaran', ['norm' => $p['norm'], 'tanggal' => $tanggal, 'jenispasien' => request('jenispasien')]) }}">Pilih</a></td>
              </tr>
            @empty
              <tr><td colspan="4"><small class="redup">Pasien "{{ $kata }}" tidak ditemukan. Isi data pasien baru di bawah.</small></td></tr>
            @endforelse
            </tbody>
          </table>
        </div>
      @endif

      <details style="margin-top:12px" @if ($errors->hasAny(['nama', 'nomorkartu', 'nik', 'nohp', 'tanggal_lahir']) || ($kata !== '' && ! $hasilCari)) open @endif>
        <summary style="cursor:pointer;color:var(--pinus)">Pasien baru</summary>
        <form method="post" action="{{ route('antrol.pendaftaran.pasien') }}">@csrf
          <input type="hidden" name="tanggal" value="{{ $tanggal }}">
          <div class="baris">
            <div><label for="pb-norm">No. RM</label><input id="pb-norm" name="norm" required maxlength="20" value="{{ old('norm') }}"></div>
            <div><label for="pb-nama">Nama</label><input id="pb-nama" name="nama" required maxlength="100" value="{{ old('nama') }}"></div>
            <div><label for="pb-noka">Nomor kartu BPJS</label><input id="pb-noka" name="nomorkartu" inputmode="numeric" maxlength="13" value="{{ old('nomorkartu', ctype_digit($kata) && strlen($kata) === 13 ? $kata : '') }}"></div>
            <div><label for="pb-nik">NIK</label><input id="pb-nik" name="nik" inputmode="numeric" maxlength="16" value="{{ old('nik', ctype_digit($kata) && strlen($kata) === 16 ? $kata : '') }}"></div>
            <div><label for="pb-hp">No. HP</label><input id="pb-hp" name="nohp" maxlength="20" value="{{ old('nohp') }}"></div>
            <div><label for="pb-tgl">Tanggal lahir</label><input id="pb-tgl" name="tanggal_lahir" type="date" value="{{ old('tanggal_lahir') }}"></div>
          </div>
          <button type="submit">Simpan pasien</button>
        </form>
      </details>
    @endif
  </section>

  {{-- ============ 2/3. POLI & KONFIRMASI ============ --}}
  @if ($konfirmasi)
    <section class="panel hasil" aria-labelledby="judul-konfirmasi">
      <h2 id="judul-konfirmasi">3. Konfirmasi pendaftaran</h2>
      <p class="ket">Periksa kembali data di bawah sebelum disimpan.</p>
      @include('antrol.partials.pesan-fingerprint', ['wajibFp' => $konfirmasi['wajib_fp']])
      <table>
        <tbody>
          <tr><th style="width:170px">Pasien</th><td>{{ $konfirmasi['pasien']['nama'] }} <small class="redup">(No. RM {{ $konfirmasi['pasien']['norm'] }})</small></td></tr>
          <tr><th>Tanggal periksa</th><td>{{ $tglTampil }}</td></tr>
          <tr><th>Poli</th><td>{{ $konfirmasi['jadwal']['namapoli'] }} <small class="redup">({{ $konfirmasi['jadwal']['kodepoli'] }})</small>
            @if ($konfirmasi['wajib_fp']) <span class="lencana fp">Poli fingerprint</span>@endif</td></tr>
          <tr><th>Dokter</th><td>{{ $konfirmasi['jadwal']['namadokter'] }} <small class="redup">({{ $konfirmasi['jadwal']['kodedokter'] }})</small></td></tr>
          <tr><th>Jam praktek</th><td>{{ $konfirmasi['jadwal']['jampraktek'] }}</td></tr>
          <tr><th>Jenis pasien</th><td>{{ $konfirmasi['input']['jenispasien'] }}</td></tr>
          <tr><th>Jenis kunjungan</th><td>{{ $kunjungan[$konfirmasi['input']['jeniskunjungan']] ?? '-' }}</td></tr>
          <tr><th>No. rujukan / referensi</th><td>{{ $konfirmasi['input']['nomorreferensi'] ?: '-' }}</td></tr>
        </tbody>
      </table>
      <form method="post" action="{{ route('antrol.pendaftaran.simpan') }}">@csrf
        @foreach (['norm', 'jadwal_id', 'tanggal', 'jenispasien', 'jeniskunjungan', 'nomorreferensi'] as $f)
          <input type="hidden" name="{{ $f }}" value="{{ $konfirmasi['input'][$f] }}">
        @endforeach
        @if ($konfirmasi['wajib_fp'])
          <label style="display:flex;gap:8px;align-items:center;color:var(--tinta);font-size:14px;margin-top:12px">
            <input type="checkbox" name="konfirmasi_fp" value="1" required> Pesan poli fingerprint sudah disampaikan kepada pasien
          </label>
        @endif
        @if ($simulasi)<p class="ket" style="margin:10px 0 0">Mode simulasi: pendaftaran juga dikirim ke BPJS (Tambah Antrean).</p>@endif
        <div class="aksi" style="margin-top:12px">
          <button type="submit" style="margin-top:0">Simpan pendaftaran</button>
          <a class="tombol garis" style="margin-top:0" href="{{ route('antrol.pendaftaran', ['norm' => $konfirmasi['pasien']['norm'], 'tanggal' => $tanggal]) }}">Ubah</a>
        </div>
      </form>
    </section>
  @else
    <section class="panel" aria-labelledby="judul-poli">
      <h2 id="judul-poli">2. Poli tujuan &amp; jadwal dokter</h2>
      @include('antrol.partials.sumber-jadwal')
      @if (! $pasien)
        <p class="ket">Pilih pasien terlebih dahulu.</p>
      @endif
      @if (! $jadwal)
        <div class="peringatan" style="margin:8px 0 0">Tidak ada jadwal praktek pada {{ $tglTampil }}. Jalankan <a href="{{ route('antrol.data-uji') }}">Sinkron jadwal dari BPJS</a> untuk tanggal ini, tambahkan data jadwal (seeder <code>AntrolDataUjiSeeder</code>), atau pilih tanggal lain.</div>
      @else
      <form method="post" action="{{ route('antrol.pendaftaran.periksa') }}" id="form-daftar">@csrf
        <input type="hidden" name="norm" value="{{ $pasien['norm'] ?? '' }}">
        <input type="hidden" name="tanggal" value="{{ $tanggal }}">
        <fieldset style="border:0;padding:0;margin:0" @disabled(! $pasien)>
          <div class="baris">
            <div>
              <label for="f-poli">Poli tujuan</label>
              <select id="f-poli" required>
                <option value="">Pilih poli</option>
                @foreach ($poliList as $pl)
                  <option value="{{ $pl['kodepoli'] }}" data-fp="{{ in_array(strtoupper($pl['kodepoli']), $poliFp, true) ? 1 : 0 }}">
                    {{ $pl['namapoli'] }} ({{ $pl['kodepoli'] }}){{ in_array(strtoupper($pl['kodepoli']), $poliFp, true) ? ' · fingerprint' : '' }}
                  </option>
                @endforeach
              </select>
            </div>
            <div>
              <label for="f-jadwal">Dokter &amp; jam praktek</label>
              <select id="f-jadwal" name="jadwal_id" required>
                <option value="">Pilih dokter</option>
                @foreach ($jadwal as $j)
                  @php $sisaJkn = $j['kuota_jkn'] - $j['terisi_jkn']; $sisaNon = $j['kuota_nonjkn'] - $j['terisi_nonjkn']; @endphp
                  <option value="{{ $j['id'] }}" data-poli="{{ $j['kodepoli'] }}" @selected(old('jadwal_id') == $j['id'])>
                    [{{ $j['kodepoli'] }}] {{ $j['namadokter'] }} · {{ $j['jampraktek'] }} · sisa JKN {{ $sisaJkn }}/{{ $j['kuota_jkn'] }}, non JKN {{ $sisaNon }}/{{ $j['kuota_nonjkn'] }}
                  </option>
                @endforeach
              </select>
            </div>
          </div>

          <div id="pesan-fp-langsung" hidden>@include('antrol.partials.pesan-fingerprint', ['wajibFp' => true])</div>

          <label>Jenis pasien</label>
          <div class="pilihan-radio">
            @php $jenisAwal = old('jenispasien', request('jenispasien') === 'NON JKN' ? 'NON JKN' : 'JKN'); @endphp
            <label><input type="radio" name="jenispasien" value="JKN" @checked($jenisAwal === 'JKN')> JKN</label>
            <label><input type="radio" name="jenispasien" value="NON JKN" @checked($jenisAwal === 'NON JKN')> Non JKN</label>
          </div>
          <div class="baris">
            <div>
              <label for="f-kunjungan">Jenis kunjungan</label>
              <select id="f-kunjungan" name="jeniskunjungan">
                @foreach ($kunjungan as $k => $nama)
                  <option value="{{ $k }}" @selected((int) old('jeniskunjungan', 1) === $k)>{{ $k }}. {{ $nama }}</option>
                @endforeach
              </select>
            </div>
            <div><label for="f-ref">No. rujukan / referensi</label><input id="f-ref" name="nomorreferensi" maxlength="30" value="{{ old('nomorreferensi') }}"></div>
          </div>
          <button type="submit">Lanjut periksa</button>
        </fieldset>
      </form>
      @endif
    </section>
  @endif
</div>

{{-- ============ DAFTAR PENDAFTARAN ============ --}}
<section class="panel" style="margin-top:14px">
  <div style="display:flex;justify-content:space-between;align-items:baseline;gap:10px;flex-wrap:wrap">
    <h2>Pendaftaran {{ $tglTampil }} <small class="redup">({{ count($daftar) }})</small></h2>
    <a href="{{ route('antrol.poli') }}">Buka halaman Poli</a>
  </div>
  <div class="gulir gulir-tinggi">
    <table>
      <thead><tr><th>No. antrean</th><th>Kode booking</th><th>Pasien</th><th>Poli</th><th>Dokter · jam</th><th>Jenis</th><th>Status</th>@if ($simulasi)<th>BPJS</th>@endif</tr></thead>
      <tbody>
      @forelse ($daftar as $a)
        <tr class="{{ $a['status'] === 'batal' ? 'redup' : '' }}">
          <td class="no-antrean">{{ $a['nomorantrean'] ?? '-' }}</td>
          <td>{{ $a['kodebooking'] }}</td>
          <td>{{ $a['nama'] ?? '-' }}<br><small class="redup">{{ $a['norm'] ?? $a['nomorkartu'] }}</small></td>
          <td>{{ $a['namapoli'] ?? $a['kodepoli'] ?? '-' }} @if ($a['wajib_fp'])<span class="lencana fp">FP</span>@endif</td>
          <td>{{ $a['namadokter'] ?? '-' }}<br><small class="redup">{{ $a['jampraktek'] }}</small></td>
          <td>{{ $a['jenispasien'] }}</td>
          <td>@include('antrol.partials.lencana-status', ['status' => $a['status']])</td>
          @if ($simulasi)<td>{!! $a['dikirim_bpjs'] ? '<span class="lencana ok">terkirim</span>' : '<span class="lencana">belum</span>' !!}</td>@endif
        </tr>
      @empty
        <tr><td colspan="8"><small class="redup">Belum ada pendaftaran.</small></td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</section>
@endsection

@push('skrip')
<script>
(function () {
  var poli = document.getElementById('f-poli');
  var jadwal = document.getElementById('f-jadwal');
  var pesan = document.getElementById('pesan-fp-langsung');
  if (!poli || !jadwal) return;

  // Pilih poli otomatis jika jadwal sudah terpilih (mis. setelah validasi gagal).
  var awal = jadwal.options[jadwal.selectedIndex];
  if (awal && awal.dataset.poli) poli.value = awal.dataset.poli;

  function saring() {
    var kode = poli.value;
    var pilih = jadwal.options[jadwal.selectedIndex];
    Array.prototype.forEach.call(jadwal.options, function (o) {
      if (!o.value) return;
      var cocok = !kode || o.dataset.poli === kode;
      o.hidden = !cocok; o.disabled = !cocok;
    });
    if (pilih && pilih.value && pilih.disabled) jadwal.value = '';
    // Pilih otomatis jika hanya ada satu dokter.
    var tersedia = Array.prototype.filter.call(jadwal.options, function (o) { return o.value && !o.disabled; });
    if (kode && tersedia.length === 1) jadwal.value = tersedia[0].value;
    var opsi = poli.options[poli.selectedIndex];
    pesan.hidden = !(opsi && opsi.dataset.fp === '1');
  }
  poli.addEventListener('change', saring);
  saring();
})();
</script>
@endpush
