{{-- Hasil pembuatan antrean farmasi onsite: nomor, kode booking, jenis resep, dan hasil Tambah Antrean Farmasi. --}}
<section class="tiket" aria-label="Hasil buat antrean farmasi">
  <div><span>Nomor antrean farmasi</span><div class="besar">{{ $hasilAmbil['nomorantrean'] }}</div></div>
  <div><span>Kode booking</span><b>{{ $hasilAmbil['kodebooking'] }}</b></div>
  <div><span>Jenis resep</span><b>{{ $hasilAmbil['jenisresep'] }}</b></div>
  <div><span>Tambah Antrean Farmasi ke BPJS</span>
    @if ($hasilAmbil['terkirim'])
      <span class="lencana ok">terkirim</span>
    @else
      <span class="lencana tidak">gagal</span>
      @if ($hasilAmbil['bpjs'])
        <small style="display:block;max-width:60ch;color:var(--merah)">{{ \Illuminate\Support\Str::limit(\App\Services\Antrol\WsbpjsService::pesan($hasilAmbil['bpjs']), 140) }}</small>
      @endif
      <small class="redup" style="display:block">Gunakan tombol Kirim ulang di tabel.</small>
    @endif
  </div>
  @if ($hasilAmbil['bpjs'])
    <div style="flex-basis:100%">@include('antrol.partials.bukti-bpjs', ['hasil' => $hasilAmbil['bpjs'], 'judul' => 'Tambah Antrean Farmasi'])</div>
  @endif
</section>
