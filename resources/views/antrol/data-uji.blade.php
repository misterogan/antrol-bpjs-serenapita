@extends('antrol.layout')
@section('judul', 'Uji service')
@section('isi')
@include('antrol.partials.tab-uji')
<h1>Data uji WSRS &amp; antrean farmasi</h1>
<p class="sub">Data antrean poli uji untuk skenario WSRS di mode sumber data lokal. Di mode <code>ANTROL_SUMBER_DATA=bpjs</code>, data antrean diperiksa ke BPJS dan data resep dari SIMRS, sehingga halaman ini tidak diperlukan.</p>


<div class="grid">
  <section class="panel">
    <h2>Tambah antrean poli uji</h2>
    <p class="ket">Untuk skenario UAT, buat beberapa kondisi: belum task ID 5, tanpa resep, sudah batal, dan siap ambil farmasi.</p>
    <form method="post" action="{{ route('antrol.data-uji.poli') }}">@csrf
      <div class="baris">
        <div><label for="du-kb">Kode booking</label><input id="du-kb" name="kodebooking" placeholder="otomatis"></div>
        <div><label for="du-tgl">Tanggal</label><input id="du-tgl" name="tanggal" type="date" value="{{ now()->toDateString() }}" required></div>
        <div><label for="du-noka">Nomor kartu</label><input id="du-noka" name="nomorkartu"></div>
        <div><label for="du-poli">Kode poli</label><input id="du-poli" name="kodepoli"></div>
      </div>
      <label for="du-jr">Jenis resep</label>
      <select id="du-jr" name="jenisresep"><option value="">(belum ada)</option><option>Racikan</option><option>Non Racikan</option><option>Tidak ada</option></select>
      <label><input type="checkbox" name="checkin" value="1" style="width:auto"> Sudah check in</label>
      <label><input type="checkbox" name="taskid5" value="1" style="width:auto"> Pelayanan poli selesai (task ID 5)</label>
      <button type="submit">Simpan</button>
    </form>
  </section>

  <section class="panel">
    <h2>Antrean farmasi hari ini</h2>
    <p class="ket">Panggil dan selesaikan antrean agar nilai antrean dipanggil &amp; sisa antrean di status antrean farmasi berubah.</p>
    <div class="gulir"><table>
      <thead><tr><th>No</th><th>Kode booking</th><th>Resep</th><th>Status</th><th></th></tr></thead>
      <tbody>
      @forelse ($farmasi as $f)
        <tr>
          <td>{{ $f->nomorantrean }}</td><td>{{ $f->kodebooking }}</td><td>{{ $f->jenisresep }}</td>
          <td>{{ $f->status }}@if($f->sumber === 'wsrs')<br><small class="redup">Dari Mobile JKN</small>@elseif($f->dikirim_bpjs_at)<br><small class="redup">terkirim ke BPJS</small>@endif</td>
          <td>
            <form method="post" action="{{ route('antrol.data-uji.farmasi.aksi', $f) }}" style="display:flex;gap:6px">@csrf
              @if ($f->status === 'menunggu')<button class="kecil" name="aksi" value="panggil">Panggil</button>@endif
              @if ($f->status === 'dipanggil')<button class="kecil" name="aksi" value="selesai">Selesai</button>@endif
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="5"><small class="redup">Belum ada antrean farmasi hari ini.</small></td></tr>
      @endforelse
      </tbody>
    </table></div>
  </section>
</div>

<section class="panel" style="margin-top:16px">
  <h2>Antrean poli uji</h2>
  <div class="gulir"><table>
    <thead><tr><th>Kode booking</th><th>Tanggal</th><th>Poli</th><th>Check in</th><th>Task ID 5</th><th>Resep</th><th>Batal</th><th></th></tr></thead>
    <tbody>
    @forelse ($poli as $p)
      <tr>
        <td>{{ $p->kodebooking }}</td><td>{{ $p->tanggal->format('d-m-Y') }}</td><td>{{ $p->kodepoli }}</td>
        <td>{{ $p->checkin ? 'ya' : '-' }}</td><td>{{ $p->taskid5_at?->format('H:i') ?? '-' }}</td>
        <td>{{ $p->jenisresep ?? '-' }}</td><td>{{ $p->batal ? 'ya' : '-' }}</td>
        <td>
          <form method="post" action="{{ route('antrol.data-uji.poli.aksi', $p) }}" style="display:flex;gap:6px;flex-wrap:wrap">@csrf
            @unless ($p->checkin)<button class="kecil" name="aksi" value="checkin">Check in</button>@endunless
            @unless ($p->taskid5_at)<button class="kecil" name="aksi" value="taskid5">Task ID 5</button>@endunless
            @unless ($p->batal)<button class="kecil" name="aksi" value="batal">Batalkan</button>@endunless
          </form>
        </td>
      </tr>
    @empty
      <tr><td colspan="8"><small class="redup">Belum ada data uji.</small></td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>

<p class="sub" style="margin-top:16px">Log request &amp; response ada di halaman <a href="{{ route('antrol.log') }}">Log bridging</a>.</p>
@endsection
