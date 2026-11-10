@extends('antrol.layout')
@section('judul', 'Cek Peserta Fingerprint')
@section('isi')
@include('antrol.partials.tab-pendaftaran')

<section class="panel">
  <h2>Cek Peserta Fingerprint</h2>
  <p class="ket">Memanggil WSBPJS Referensi Pasien Fingerprint untuk mengetahui apakah peserta sudah terdaftar fingerprint.</p>
  <form method="post" action="{{ route('antrol.pendaftaran.peserta-fp.cek') }}" class="filter">@csrf
    <div><label for="pfp-jenis">Jenis identitas</label>
      <select id="pfp-jenis" name="jenis">
        <option value="noka" @selected(old('jenis', 'noka') === 'noka')>Nomor kartu</option>
        <option value="nik" @selected(old('jenis') === 'nik')>NIK</option>
      </select></div>
    <div style="min-width:240px"><label for="pfp-nomor">Nomor</label><input id="pfp-nomor" name="nomor" required inputmode="numeric" maxlength="16" value="{{ old('nomor') }}"></div>
    <button type="submit">Cek peserta</button>
  </form>
</section>

@if ($cek)
  @php
    $hasilPs = $cek['hasil'];
    $kodePs = $hasilPs['metadata']['code'] ?? null;
    $adaJawaban = $kodePs !== null && $kodePs !== '';
  @endphp
  <section class="panel hasil {{ $adaJawaban ? '' : 'gagal' }}" style="margin-top:14px;max-width:760px">
    <h2>Hasil Referensi Pasien Fingerprint <span class="kode">code {{ $kodePs ?? '-' }}</span></h2>
    <p style="margin:4px 0 10px">{{ $hasilPs['metadata']['message'] ?? ($hasilPs['error'] ?? 'Tidak ada respons dari BPJS') }}
      <small class="redup">· dicek dengan {{ $cek['jenis'] === 'nik' ? 'NIK' : 'nomor kartu' }} {{ $cek['nomor'] }} pukul {{ $cek['waktu'] }}</small></p>
    <div class="kartu-pasien">
      <dl>
        <dt>NIK</dt><dd>{{ $ringkas['nik'] ?? '-' }}</dd>
        <dt>Nomor kartu</dt><dd>{{ $ringkas['nomorkartu'] ?? '-' }}</dd>
        <dt>Tanggal lahir</dt><dd>{{ $ringkas['tgllahir'] ?? '-' }}</dd>
        <dt>Status fingerprint</dt>
        <dd>
          @if ($ringkas['daftarfp'] !== null)
            daftarfp = <b>{{ $ringkas['daftarfp'] }}</b>{{ $ringkas['keterangan_daftarfp'] ? ' ('.$ringkas['keterangan_daftarfp'].')' : '' }}
          @else
            -
          @endif
        </dd>
      </dl>
    </div>
    @include('antrol.partials.bukti-bpjs', ['hasil' => $hasilPs, 'judul' => 'Referensi Pasien Fingerprint'])
  </section>
@endif
@endsection
