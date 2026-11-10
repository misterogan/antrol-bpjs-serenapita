{{-- Baris kecil "N pasien siap dibuatkan antrean" + daftar yang bisa dibuka. Tidak tampil jika kosong. --}}
@if (count($siap) > 0)
  <div class="siap-baris">
    <b>{{ count($siap) }}</b> pasien {{ $modeBpjs ? 'dari SIMRS' : 'dari data uji' }} siap dibuatkan antrean.
    <button type="button" class="tautan" data-buka="daftar-siap" aria-expanded="false" aria-controls="daftar-siap" data-boleh-ulang>Lihat daftar</button>
  </div>
  <div id="daftar-siap" class="panel" hidden style="margin:-4px 0 12px;padding:8px 12px">
    <table>
      <thead><tr><th>Nama pasien</th><th>No. RM</th><th>Kode booking</th><th>Jenis resep</th><th></th></tr></thead>
      <tbody>
      @foreach ($siap as $s)
        <tr>
          <td>{{ $s['nama'] ?? '(nama belum dikirim SIMRS)' }}</td>
          <td>{{ $s['norm'] ?? '-' }}</td>
          <td><small>{{ $s['kodebooking'] }}</small></td>
          <td>{{ $s['jenisresep'] }}</td>
          <td style="text-align:right">
            <button type="button" class="kecil" data-buat-antrean
                    data-kodebooking="{{ $s['kodebooking'] }}" data-nama="{{ $s['nama'] }}" data-norm="{{ $s['norm'] }}" data-jenis="{{ $s['jenisresep'] }}"
                    data-label="{{ $modeBpjs ? 'data dari SIMRS' : 'data dari data uji poli' }}">Buat antrean</button>
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
@endif
