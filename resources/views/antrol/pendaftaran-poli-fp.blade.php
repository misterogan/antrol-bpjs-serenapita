@extends('antrol.layout')
@section('judul', 'Cek Poli Fingerprint')
@section('isi')
@include('antrol.partials.tab-pendaftaran')

<section class="panel">
  <h2>Cek Poli Fingerprint</h2>
  <p class="ket">Memanggil WSBPJS Referensi Poli Fingerprint langsung ke BPJS (tanpa cache). Hasil yang berhasil juga memperbarui daftar poli fingerprint yang dipakai pesan di pendaftaran.</p>
  <form method="post" action="{{ route('antrol.pendaftaran.poli-fp.cek') }}">@csrf
    <button type="submit">Cek poli fingerprint</button>
  </form>
</section>

@if ($cek)
  @php
    $hasilFp = $cek['hasil'];
    $suksesFp = \App\Services\Antrol\WsbpjsService::berhasil($hasilFp);
  @endphp
  <section class="panel hasil {{ $suksesFp ? '' : 'gagal' }}" style="margin-top:14px">
    <h2>Hasil Referensi Poli Fingerprint <span class="kode">code {{ $hasilFp['metadata']['code'] ?? '-' }}</span></h2>
    <p style="margin:4px 0 10px">
      {{ $hasilFp['metadata']['message'] ?? ($hasilFp['error'] ?? 'Tidak ada respons dari BPJS') }}
      · <b>{{ count($baris) }}</b> poli · <small class="redup">dicek {{ $cek['waktu'] }}</small>
    </p>
    @if ($baris)
      <div class="gulir gulir-tinggi"><table>
        <thead><tr><th class="angka">No</th><th>Kode Poli</th><th>Nama Poli</th><th>Kode Subspesialis</th><th>Nama Subspesialis</th></tr></thead>
        <tbody>
        @foreach ($baris as $i => $b)
          <tr><td class="angka">{{ $i + 1 }}</td><td>{{ $b['kodepoli'] }}</td><td>{{ $b['namapoli'] }}</td><td>{{ $b['kodesubspesialis'] }}</td><td>{{ $b['namasubspesialis'] }}</td></tr>
        @endforeach
        </tbody>
      </table></div>
    @else
      <small class="redup">Tidak ada data poli pada respons.</small>
    @endif
    @include('antrol.partials.bukti-bpjs', ['hasil' => $hasilFp, 'judul' => 'Referensi Poli Fingerprint'])
  </section>
@endif
@endsection
