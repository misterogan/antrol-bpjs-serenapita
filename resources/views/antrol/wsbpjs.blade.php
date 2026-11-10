@extends('antrol.layout')
@section('judul', 'Uji service')
@section('isi')
@include('antrol.partials.tab-uji')
<h1>Service WS BPJS</h1>
<p class="sub">Lima service BPJS yang belum lolos UAT. Setiap hasil menampilkan request, metadata, dan response yang sudah didekripsi, siap di-capture untuk form UAT. Semua panggilan tercatat di halaman <a href="{{ route('antrol.log') }}">Log bridging</a>.</p>

@if (!empty($hasil))
  @include('antrol.partials.hasil', ['hasil' => $hasil, 'judul' => $judul])
@endif

@if (!empty($pendaftaran))
  <section class="panel hasil">
    <h2>Simulasi pendaftaran ke poli {{ $pendaftaran['kodepoli'] }}</h2>
    @include('antrol.partials.pesan-fingerprint', ['wajibFp' => $pendaftaran['wajib_fp']])
    @unless ($pendaftaran['wajib_fp'])
      <p>Poli ini tidak termasuk poli fingerprint. Pendaftaran dilanjutkan seperti biasa.</p>
    @endunless
    <small class="redup">Poli fingerprint dari HFIS: {{ implode(', ', $pendaftaran['daftar_fp']) ?: '(kosong / gagal dibaca)' }}</small>
    @if ($pendaftaran['pasien'])
      <label>Referensi pasien fingerprint</label>
      <pre>{{ json_encode($pendaftaran['pasien']['response'] ?? $pendaftaran['pasien']['metadata'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    @endif
  </section>
@endif

<div class="grid">
  <section class="panel">
    <h2>Referensi Poli Fingerprint</h2>
    <p class="ket">Poli yang disetting wajib fingerprint di HFIS.</p>
    <form method="post" action="{{ route('antrol.poli-fp') }}">@csrf
      <button type="submit">Ambil referensi</button>
    </form>
  </section>

  <section class="panel">
    <h2>Referensi Pasien Fingerprint</h2>
    <p class="ket">Cek apakah pasien termasuk kategori yang wajib fingerprint.</p>
    <form method="post" action="{{ route('antrol.pasien-fp') }}">@csrf
      <div class="baris">
        <div><label for="pf-jenis">Jenis identitas</label>
          <select id="pf-jenis" name="jenis"><option value="noka">Nomor kartu</option><option value="nik">NIK</option></select></div>
        <div><label for="pf-nomor">Nomor</label><input id="pf-nomor" name="nomor" required value="{{ old('nomor') }}"></div>
      </div>
      <button type="submit">Cek pasien</button>
    </form>
  </section>

  @if (config('antrol.fitur_farmasi'))
  <section class="panel">
    <h2>Tambah Antrean Farmasi</h2>
    <p class="ket">Kosongkan nomor antrean agar nomor diambil otomatis dari antrean farmasi RS (dengan validasi task ID 5 &amp; jenis resep), lalu dikirim ke BPJS.</p>
    <form method="post" action="{{ route('antrol.tambah-farmasi') }}">@csrf
      <label for="tf-kb">Kode booking poli</label><input id="tf-kb" name="kodebooking" required value="{{ old('kodebooking') }}">
      <div class="baris">
        <div><label for="tf-jr">Jenis resep</label>
          <select id="tf-jr" name="jenisresep"><option value="">(otomatis)</option><option value="racikan">Racikan</option><option value="non racikan">Non racikan</option></select></div>
        <div><label for="tf-no">Nomor antrean</label><input id="tf-no" name="nomorantrean" type="number" min="1" placeholder="otomatis"></div>
      </div>
      <label for="tf-ket">Keterangan</label><input id="tf-ket" name="keterangan" value="{{ old('keterangan') }}" placeholder="{{ config('antrol.keterangan_farmasi_default') }}">
      <small class="redup" style="display:block;margin-top:4px">Jika kosong, dikirim keterangan default di atas.</small>
      <button type="submit">Tambah antrean</button>
    </form>
  </section>
  @endif

  <section class="panel">
    <h2>Antrean Belum Dilayani</h2>
    <p class="ket">Seluruh antrean RS yang belum dilayani menurut data BPJS.</p>
    <form method="post" action="{{ route('antrol.belum-dilayani') }}">@csrf
      <button type="submit">Tampilkan</button>
    </form>
  </section>

  <section class="panel">
    <h2>Antrean Belum Dilayani per Poli, Dokter, Hari, Jam Praktek</h2>
    <p class="ket">Kode poli dan dokter mengikuti HFIS. Hari: 1 = Senin s.d. 7 = Minggu.</p>
    <form method="post" action="{{ route('antrol.belum-dilayani-poli') }}">@csrf
      <div class="baris">
        <div><label for="bp-poli">Kode poli</label><input id="bp-poli" name="kodepoli" required value="{{ old('kodepoli') }}"></div>
        <div><label for="bp-dok">Kode dokter</label><input id="bp-dok" name="kodedokter" required value="{{ old('kodedokter') }}"></div>
        <div><label for="bp-hari">Hari</label><input id="bp-hari" name="hari" type="number" min="1" max="8" required value="{{ old('hari', now()->dayOfWeekIso) }}"></div>
        <div><label for="bp-jam">Jam praktek</label><input id="bp-jam" name="jampraktek" placeholder="08:00-12:00" required value="{{ old('jampraktek') }}"></div>
      </div>
      <button type="submit">Tampilkan</button>
    </form>
  </section>

  <section class="panel">
    <h2>Simulasi pesan poli fingerprint</h2>
    <p class="ket">Meniru layar pendaftaran: pesan muncul jika poli tujuan termasuk poli fingerprint.</p>
    <form method="post" action="{{ route('antrol.cek-pendaftaran') }}">@csrf
      <label for="cp-poli">Kode poli tujuan</label><input id="cp-poli" name="kodepoli" required>
      <div class="baris">
        <div><label for="cp-jenis">Identitas pasien (opsional)</label>
          <select id="cp-jenis" name="jenis"><option value="noka">Nomor kartu</option><option value="nik">NIK</option></select></div>
        <div><label for="cp-nomor">Nomor</label><input id="cp-nomor" name="nomor"></div>
      </div>
      <button type="submit">Cek</button>
    </form>
  </section>
</div>

<h2 style="font-size:16px;margin:22px 0 4px">Service yang sudah lolos UAT</h2>
<p class="sub">Untuk pengecekan data antrean di BPJS. Hasilnya tampil dengan format yang sama di atas.</p>
<div class="grid">
  <section class="panel">
    <h2>Antrean Per Kode Booking</h2>
    <p class="ket">Data antrean di BPJS untuk satu kode booking.</p>
    <form method="post" action="{{ route('antrol.antrean-kodebooking') }}">@csrf
      <label for="ak-kb">Kode booking</label><input id="ak-kb" name="kodebooking" required maxlength="30" value="{{ old('kodebooking') }}">
      <button type="submit">Tampilkan</button>
    </form>
  </section>

  <section class="panel">
    <h2>List Waktu Task ID</h2>
    <p class="ket">Waktu setiap task ID yang sudah diterima BPJS untuk satu kode booking.</p>
    <form method="post" action="{{ route('antrol.list-task') }}">@csrf
      <label for="lt-kb">Kode booking</label><input id="lt-kb" name="kodebooking" required maxlength="30" value="{{ old('kodebooking') }}">
      <button type="submit">Tampilkan</button>
    </form>
  </section>
</div>
@endsection
