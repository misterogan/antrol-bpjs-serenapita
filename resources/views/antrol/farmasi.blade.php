@extends('antrol.layout')
@section('judul', 'Antrian Farmasi')
@push('gaya')
<style>
.siap-baris{background:#fff;border:1px dashed var(--toska);border-radius:3px;padding:6px 12px;margin:0 0 12px;font-size:13px}
button.tautan{background:none;border:0;box-shadow:none;color:var(--pinus);text-decoration:underline;padding:0;margin:0 0 0 6px;font-size:13px;font-weight:400}
button.tautan:hover{background:none;color:var(--pinus-tua)}
dialog.dialog-farmasi{width:540px;max-width:calc(100vw - 32px);border:0;border-radius:4px;padding:18px 20px;box-shadow:0 10px 30px rgba(0,0,0,.25)}
dialog.dialog-farmasi::backdrop{background:rgba(30,40,45,.45)}
dialog.dialog-farmasi h2{font-size:16px;font-weight:500;margin:0 0 4px}
.dialog-tombol{display:flex;gap:8px;justify-content:flex-end;margin-top:14px}
.dialog-tombol button{margin:0}
tr.baru td{animation:sorot 4s ease-out}
@keyframes sorot{from{background:#FFF1BF}to{background:transparent}}
@media (prefers-reduced-motion:reduce){tr.baru td{animation:none;background:#FFF8DD}}
</style>
@endpush
@section('isi')
<div class="kepala">
  <div>
    <h1>Antrian Farmasi</h1>
    <p class="sub">Antrean farmasi hari ini, dari pasien onsite maupun Mobile JKN. Panggil dan Selesai mengirim Update Waktu task ID 6 dan 7 ke BPJS{{ config('antrol.kirim_task_farmasi') ? '' : ' (dimatikan di konfigurasi)' }}.</p>
  </div>
  <div style="display:flex;gap:10px;align-items:center">
    <small class="redup" id="farmasi-diperbarui" aria-live="polite">Diperbarui {{ now()->format('H:i:s') }}</small>
    <button type="button" class="garis kecil" id="farmasi-muat-ulang" data-boleh-ulang>Muat ulang</button>
    <button type="button" id="tombol-buat-antrean" style="margin:0" data-boleh-ulang>+ Buat antrean farmasi</button>
  </div>
</div>

<div class="statistik" id="farmasi-ringkasan" aria-label="Ringkasan antrean farmasi hari ini">
  @include('antrol.partials.farmasi-ringkasan')
</div>

<div id="farmasi-notifikasi" aria-live="polite">
  @if ($hasilAmbil)
    @include('antrol.partials.farmasi-tiket')
  @endif
</div>

<div id="farmasi-siap">
  @include('antrol.partials.farmasi-siap')
</div>

<section class="panel">
  <h2>Antrean farmasi hari ini</h2>
  <div class="gulir gulir-tinggi">
    <table>
      <thead><tr>
        <th class="angka">No.</th><th>Nama pasien</th><th>No. RM</th><th>Kode booking</th><th>Jenis resep</th>
        <th>Sumber</th><th>Status</th><th>Waktu</th><th>Status BPJS</th><th style="min-width:150px">Aksi</th>
      </tr></thead>
      <tbody id="farmasi-baris">
        @include('antrol.partials.farmasi-baris')
      </tbody>
    </table>
  </div>
</section>

<dialog id="dialog-buat" class="dialog-farmasi" aria-labelledby="judul-dialog-buat">
  <form method="post" action="{{ route('antrol.farmasi.buat') }}" id="form-buat">@csrf
    <h2 id="judul-dialog-buat">Buat antrean farmasi</h2>
    <p class="ket redup" style="margin:0 0 6px;font-size:13px">Untuk pasien onsite. Aturannya sama dengan WSRS ambil antrean farmasi{{ $modeBpjs ? ' (diperiksa ke BPJS dan data resep)' : '' }}, nomor diambil dari {{ config('antrol.nomor_farmasi_dari', 'vendor') === 'vendor' ? 'SIMRS vendor' : 'urutan modul ini' }}, lalu dikirim ke BPJS lewat Tambah Antrean Farmasi.</p>
    <div id="form-buat-galat" class="galat" role="alert" @unless (session('buka_form') && $errors->has('farmasi')) hidden @endunless>{{ session('buka_form') ? $errors->first('farmasi') : '' }}</div>

    <label for="fb-kodebooking">Kode booking poli <span style="color:var(--merah)">*</span>
      <span id="fb-sumber" class="lencana ok" style="margin-left:6px" hidden>data dari SIMRS</span></label>
    <input id="fb-kodebooking" name="kodebooking" required maxlength="30" autocomplete="off" value="{{ session('buka_form') ? old('kodebooking') : '' }}">
    <div class="baris">
      <div><label for="fb-nama">Nama pasien</label><input id="fb-nama" name="namapasien" maxlength="100" autocomplete="off" value="{{ session('buka_form') ? old('namapasien') : '' }}"></div>
      <div><label for="fb-norm">No. RM</label><input id="fb-norm" name="norekammedis" maxlength="30" autocomplete="off" value="{{ session('buka_form') ? old('norekammedis') : '' }}"></div>
    </div>
    <label for="fb-jenis">Jenis resep <span style="color:var(--merah)">*</span></label>
    <select id="fb-jenis" name="jenisresep" required>
      <option value="">Pilih jenis resep</option>
      @foreach (['Racikan', 'Non Racikan'] as $j)<option @selected(session('buka_form') && old('jenisresep') === $j)>{{ $j }}</option>@endforeach
    </select>
    <label for="fb-keterangan">Keterangan <span style="color:var(--merah)">*</span></label>
    <input id="fb-keterangan" name="keterangan" required maxlength="200" value="{{ session('buka_form') ? old('keterangan', config('antrol.keterangan_farmasi_default')) : config('antrol.keterangan_farmasi_default') }}">

    <div class="dialog-tombol">
      <button type="button" class="garis" id="fb-batal" data-boleh-ulang>Batal</button>
      <button type="submit" id="fb-simpan">Simpan</button>
    </div>
  </form>
</dialog>
@endsection
@push('skrip')
<script>
(function () {
  var urlData = @json(route('antrol.farmasi.data'));
  var urlResep = @json(url('antrol/farmasi/resep')) + '/';
  var keteranganDefault = @json(config('antrol.keterangan_farmasi_default'));
  var baris = document.getElementById('farmasi-baris');
  var ringkasan = document.getElementById('farmasi-ringkasan');
  var siap = document.getElementById('farmasi-siap');
  var label = document.getElementById('farmasi-diperbarui');
  var notifikasi = document.getElementById('farmasi-notifikasi');
  var dialog = document.getElementById('dialog-buat');
  var form = document.getElementById('form-buat');
  var galat = document.getElementById('form-buat-galat');
  var sumber = document.getElementById('fb-sumber');
  var isian = { kode: form.kodebooking, nama: form.namapasien, norm: form.norekammedis, jenis: form.jenisresep };

  // Pembaruan otomatis tabel, ringkasan, dan daftar siap setiap 10 detik (fetch JSON, tanpa build step).
  function muat() {
    if (document.hidden) { return Promise.resolve(); }
    var daftar = document.getElementById('daftar-siap');
    var daftarTerbuka = daftar && !daftar.hidden;
    return fetch(urlData, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); })
      .then(function (d) {
        baris.innerHTML = d.baris;
        ringkasan.innerHTML = d.ringkasan;
        siap.innerHTML = d.siap;
        var baru = document.getElementById('daftar-siap');
        if (baru && daftarTerbuka) { baru.hidden = false; }
        label.textContent = 'Diperbarui ' + d.diperbarui;
      })
      .catch(function () { label.textContent = 'Gagal memperbarui, coba Muat ulang'; });
  }
  setInterval(muat, 10000);
  document.getElementById('farmasi-muat-ulang').addEventListener('click', muat);

  function tampilkanSumber(teks) {
    sumber.textContent = teks || '';
    sumber.hidden = !teks;
  }

  function bukaForm(data) {
    data = data || {};
    form.reset();
    galat.hidden = true;
    isian.kode.value = data.kodebooking || '';
    isian.nama.value = data.nama || '';
    isian.norm.value = data.norm || '';
    isian.jenis.value = data.jenis === 'Racikan' || data.jenis === 'Non Racikan' ? data.jenis : '';
    form.keterangan.value = keteranganDefault;
    [isian.nama, isian.norm, isian.jenis].forEach(function (el) { el.dataset.otomatis = data.label ? '1' : ''; });
    tampilkanSumber(data.label);
    dialog.showModal();
    (data.kodebooking ? (isian.jenis.value ? form.querySelector('#fb-simpan') : isian.jenis) : isian.kode).focus();
  }

  document.getElementById('tombol-buat-antrean').addEventListener('click', function () { bukaForm(); });
  document.getElementById('fb-batal').addEventListener('click', function () { dialog.close(); });
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-buat-antrean]');
    if (!t) { return; }
    bukaForm({ kodebooking: t.dataset.kodebooking, nama: t.dataset.nama, norm: t.dataset.norm, jenis: t.dataset.jenis, label: t.dataset.label });
  });

  // Isi otomatis dari data resep (dari SIMRS atau isian sebelumnya) setelah kode booking diketik.
  var tunda = null;
  isian.kode.addEventListener('input', function () {
    clearTimeout(tunda);
    var kode = isian.kode.value.trim();
    if (!/^[A-Za-z0-9_-]{4,30}$/.test(kode)) { tampilkanSumber(''); return; }
    tunda = setTimeout(function () {
      fetch(urlResep + encodeURIComponent(kode), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : { ada: false }; })
        .then(function (d) {
          if (isian.kode.value.trim() !== kode) { return; }
          if (!d.ada) {
            [isian.nama, isian.norm, isian.jenis].forEach(function (el) { if (el.dataset.otomatis === '1') { el.value = ''; el.dataset.otomatis = ''; } });
            tampilkanSumber('');
            return;
          }
          [[isian.nama, d.namapasien], [isian.norm, d.norekammedis], [isian.jenis, d.jenisresep]].forEach(function (p) {
            if (p[1] && (p[0].value === '' || p[0].dataset.otomatis === '1')) { p[0].value = p[1]; p[0].dataset.otomatis = '1'; }
          });
          tampilkanSumber(d.label);
        })
        .catch(function () {});
    }, 400);
  });
  [isian.nama, isian.norm, isian.jenis].forEach(function (el) {
    el.addEventListener('input', function () { el.dataset.otomatis = ''; });
  });

  function sorot(kode) {
    var tr = baris.querySelector('tr[data-kodebooking="' + (window.CSS && CSS.escape ? CSS.escape(kode) : kode) + '"]');
    if (tr) { tr.classList.add('baru'); tr.scrollIntoView({ block: 'nearest' }); }
  }

  function aktifkanSimpan() {
    var b = document.getElementById('fb-simpan');
    setTimeout(function () { b.disabled = false; b.removeAttribute('data-sedang-kirim'); }, 0);
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    galat.hidden = true;
    window.antrolLoading.tampil('Membuat antrean farmasi...');
    fetch(form.action, {
      method: 'POST', body: new FormData(form), credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (r) { return r.json().catch(function () { return { ok: false, pesan: 'Jawaban server tidak dikenali (HTTP ' + r.status + ').' }; }); })
      .then(function (d) {
        window.antrolLoading.sembunyi();
        aktifkanSimpan();
        if (!d.ok) {
          galat.textContent = d.pesan || 'Antrean farmasi tidak dapat dibuat.';
          galat.hidden = false;
          return;
        }
        dialog.close();
        notifikasi.innerHTML = '';
        var info = document.createElement('div');
        info.className = 'info';
        info.setAttribute('role', 'status');
        info.textContent = d.pesan + (d.terkirim ? ' Tambah Antrean Farmasi terkirim ke BPJS.' : '');
        notifikasi.appendChild(info);
        if (d.peringatan) {
          var p = document.createElement('div');
          p.className = 'peringatan';
          p.textContent = d.peringatan;
          notifikasi.appendChild(p);
        }
        notifikasi.insertAdjacentHTML('beforeend', d.tiket);
        muat().then(function () { sorot(d.kodebooking); });
      })
      .catch(function () {
        window.antrolLoading.sembunyi();
        aktifkanSimpan();
        galat.textContent = 'Gagal menghubungi server. Periksa jaringan lalu coba lagi.';
        galat.hidden = false;
      });
  });

  @if (session('buka_form'))
    dialog.showModal();
  @endif
})();
</script>
@endpush
