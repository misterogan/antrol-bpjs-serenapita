@extends('antrol.layout')
@section('judul', 'Alat Uji')
@section('isi')
@include('antrol.partials.tab-uji')
<h1>Alat Uji</h1>
<p class="sub">Halaman pendukung untuk menyiapkan data dan menguji service. Tidak dipakai petugas sehari-hari.</p>
@unless (\App\Services\Antrol\PelayananService::alatSimulasi())
  <p class="sub"><small>Poli (simulasi) dan Data uji disembunyikan. Tampilkan dengan <code>ANTROL_TAMPILKAN_ALAT_SIMULASI=true</code> (khusus pengujian).</small></p>
@endunless

<div class="grid">
  @if (\App\Services\Antrol\PelayananService::alatSimulasi())
  <section class="panel">
    <h2>Poli (simulasi)</h2>
    <p class="ket">Check in, panggil, mulai, dan selesai pelayanan poli untuk data uji, termasuk jenis resep. Di mode simulasi, mengirim Update Waktu task ID 3, 4, 5.</p>
    <a class="tombol" href="{{ route('antrol.poli') }}">Buka Poli (simulasi)</a>
  </section>
  <section class="panel">
    <h2>Data uji &amp; sinkron jadwal</h2>
    <p class="ket">Membuat antrean poli uji untuk skenario WSRS dan menyinkronkan jadwal dokter dari HFIS (Referensi Poli &amp; Referensi Jadwal Dokter).</p>
    <a class="tombol" href="{{ route('antrol.data-uji') }}">Buka Data uji</a>
  </section>
  @endif
  <section class="panel">
    <h2>Uji service</h2>
    <p class="ket">Menjalankan service WS BPJS satu per satu (termasuk Antrean Per Kode Booking dan List Waktu Task ID) dan menampilkan request serta respons untuk capture form UAT.</p>
    <a class="tombol" href="{{ route('antrol.wsbpjs') }}">Buka Uji service</a>
  </section>
</div>
@endsection
