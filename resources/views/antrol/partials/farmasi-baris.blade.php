{{-- Baris tabel antrean farmasi hari ini (dipakai saat halaman dibuka dan saat pembaruan otomatis). --}}
@forelse ($antrean as $f)
  @php
    $mjkn = $f['sumber'] === 'wsrs';
    $selesaiAtauBatal = in_array($f['status'], ['selesai', 'batal'], true);
  @endphp
  <tr class="{{ $selesaiAtauBatal ? 'redup' : '' }}" data-kodebooking="{{ $f['kodebooking'] }}">
    <td class="angka no-antrean" style="font-size:20px">{{ $f['nomorantrean'] }}</td>
    <td>
      @if ($f['nama'])
        {{ $f['nama'] }}
      @else
        <small class="redup">(nama belum dikirim SIMRS)</small>
      @endif
    </td>
    <td>{{ $f['norm'] ?? '-' }}</td>
    <td><small>{{ $f['kodebooking'] }}</small></td>
    <td>{{ $f['jenisresep'] }}</td>
    <td>@if ($mjkn)<span class="lencana checkin">Mobile JKN</span>@else<span class="lencana">Onsite</span>@endif</td>
    <td>@include('antrol.partials.lencana-status', ['status' => $f['status']])</td>
    <td style="white-space:nowrap"><small>
      ambil {{ $f['diambil_at'] ?? '-' }}
      @if ($f['dipanggil_at'])<br>panggil {{ $f['dipanggil_at'] }}@endif
      @if ($f['selesai_at'])<br>selesai {{ $f['selesai_at'] }}@endif
      @if ($f['batal_at'])<br>batal {{ $f['batal_at'] }}@endif
    </small></td>
    <td>
      @if ($mjkn)
        <span class="lencana" title="Diambil lewat WSRS ambil antrean farmasi, sudah diketahui BPJS">Dari Mobile JKN</span>
      @elseif ($f['dikirim_bpjs'])
        <span class="lencana ok">terkirim</span>
      @elseif ($f['status'] !== 'batal')
        <form method="post" action="{{ route('antrol.farmasi.kirim-ulang', $f['kodebooking']) }}">@csrf
          <button class="kecil garis" title="Kirim ulang Tambah Antrean Farmasi">Kirim ulang</button>
        </form>
      @else
        <small class="redup">-</small>
      @endif
      @if ($kirimTask)
        @foreach ([6 => ['dipanggil', 'selesai'], 7 => ['selesai']] as $task => $statusTask)
          @if (in_array($f['status'], $statusTask, true))
            <div style="margin-top:3px;font-size:12px">
              Task {{ $task }}:
              @if ($f['task'.$task.'_at'])
                <span style="color:var(--pinus)">terkirim {{ $f['task'.$task.'_at'] }}</span>
              @else
                @if ($f['task'.$task.'_pesan'])
                  <span style="color:var(--merah)" title="{{ $f['task'.$task.'_pesan'] }}">gagal: {{ \Illuminate\Support\Str::limit($f['task'.$task.'_pesan'], 60) }}</span>
                @else
                  <span class="redup">belum dikirim</span>
                @endif
                <form method="post" action="{{ route('antrol.farmasi.kirim-task', [$f['kodebooking'], $task]) }}" style="display:inline">@csrf
                  <button class="kecil garis" style="padding:0 6px;font-size:11.5px">Kirim ulang task</button>
                </form>
              @endif
            </div>
          @endif
        @endforeach
      @endif
    </td>
    <td>
      <div class="aksi">
        @if ($f['status'] === 'menunggu')
          <form method="post" action="{{ route('antrol.farmasi.aksi', [$f['kodebooking'], 'panggil']) }}">@csrf<button class="kecil">Panggil</button></form>
          <form method="post" action="{{ route('antrol.farmasi.aksi', [$f['kodebooking'], 'batal']) }}" data-konfirmasi="Batalkan antrean farmasi nomor {{ $f['nomorantrean'] }}?">@csrf<button class="kecil bahaya">Batalkan</button></form>
        @elseif ($f['status'] === 'dipanggil')
          <form method="post" action="{{ route('antrol.farmasi.aksi', [$f['kodebooking'], 'panggil']) }}">@csrf<button class="kecil garis">Panggil ulang</button></form>
          <form method="post" action="{{ route('antrol.farmasi.aksi', [$f['kodebooking'], 'selesai']) }}">@csrf<button class="kecil">Selesai</button></form>
        @else
          <small class="redup">-</small>
        @endif
        @if ($cekStatusVendor ?? false)
          <form method="post" action="{{ route('antrol.farmasi.cek-status', $f['kodebooking']) }}">@csrf<button class="kecil garis" title="Status Antrean Farmasi dari SIMRS vendor">Cek status</button></form>
        @endif
      </div>
    </td>
  </tr>
@empty
  <tr><td colspan="10"><small class="redup">Belum ada antrean farmasi hari ini.</small></td></tr>
@endforelse
