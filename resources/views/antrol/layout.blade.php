<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('judul', 'Modul Antrol') — Modul Antrean Online</title>
<style>
:root{
  /* Gaya mengikuti SIMRS RS: latar abu-abu muda, header putih, aksen hijau toska. */
  --kertas:#F7F7F7; --permukaan:#FFFFFF; --tinta:#2E3338; --redup:#6B747B;
  --garis:#E3E6E8; --toska:#38BF9D; --toska-tua:#2BA785;
  --pinus:#17876C; --pinus-tua:#0F6B55; --pinus-muda:#E3F5EF;
  --biru-tua:#0F4C81; --navi:#1F3A5F;
  --amber:#9A5B00; --amber-muda:#FFF3DC;
  --merah:#B03A2E; --merah-muda:#FBE7E5; --biru:#1F5A96; --biru-muda:#E4EEF8; --abu-muda:#EEF0F1;
}
*{box-sizing:border-box}
[hidden]{display:none!important}
body{margin:0;background:var(--kertas);color:var(--tinta);font:14px/1.5 Roboto,"Segoe UI","Noto Sans",Arial,sans-serif}
.ikon{flex:none;vertical-align:-2px}

/* Header */
.kop{background:#fff;box-shadow:0 2px 4px rgba(0,0,0,.10);position:relative;z-index:2}
.kop-dalam{display:flex;align-items:stretch;min-height:76px;padding:0 24px 0 0}
.kop-logo{width:132px;flex:none;display:flex;align-items:center;justify-content:center;text-decoration:none}
.kop-logo img{max-height:54px;max-width:110px}
.kop-isi{flex:1;display:flex;flex-direction:column;justify-content:center;gap:8px;min-width:0}
.kop-atas,.kop-bawah{display:flex;align-items:center;gap:14px}
.kop-nama{font-size:15px;color:#222;letter-spacing:.2px;white-space:nowrap}
.kop-modul{font-size:11px;color:var(--pinus);background:var(--pinus-muda);border-radius:3px;padding:1px 7px;white-space:nowrap}
.kop-pengguna{margin-left:auto;display:flex;align-items:center;gap:5px;font-size:12.5px;color:#333;white-space:nowrap}
.kop-label{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.label-mode{font-size:11.5px;color:#444;background:var(--abu-muda);border-radius:3px;padding:2px 8px;white-space:nowrap}
.label-mode.dev{background:var(--biru-muda);color:var(--biru)}
.label-mode.prod{background:var(--merah-muda);color:var(--merah);font-weight:600}
.tombol-kembali{display:inline-flex;align-items:center;gap:6px;color:var(--biru-tua);text-decoration:none;font-size:13px;margin:0 0 10px}
.tombol-kembali:hover{color:var(--pinus)}
.kop-kanan{margin-left:auto;display:flex;gap:14px;align-items:center;font-size:12.5px;color:#333;white-space:nowrap}
.kop-kanan span{display:flex;align-items:center;gap:5px}
.label-simulasi{background:var(--amber-muda);color:#6E4100;border:1px solid #E9C98C;font-weight:700;letter-spacing:.4px;padding:2px 9px;border-radius:3px;font-size:11.5px}

main{max-width:1320px;margin:0 auto;padding:20px 24px 28px}
h1{font-size:19px;font-weight:500;margin:0 0 2px;color:#222}
.sub{color:var(--redup);margin:0 0 16px;max-width:90ch}
.kepala{display:flex;gap:16px;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;margin-bottom:14px}
.kepala .sub{margin:0}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(330px,1fr));gap:14px}
.dua-kolom{display:grid;grid-template-columns:minmax(340px,5fr) 7fr;gap:14px;align-items:start}
@media (max-width:1000px){.dua-kolom{grid-template-columns:1fr}}
.panel{background:var(--permukaan);border:1px solid var(--garis);border-radius:3px;padding:14px 16px}
.panel + .panel{margin-top:14px}
.dua-kolom > .panel + .panel,.grid > .panel + .panel{margin-top:0}
.panel h2{font-size:15px;font-weight:500;margin:0 0 4px;color:#222}
.panel h3{font-size:14px;font-weight:500;margin:12px 0 4px}
.panel p.ket{color:var(--redup);font-size:13px;margin:0 0 10px}
label{display:block;font-size:12.5px;color:var(--redup);margin:8px 0 3px}
input,select{width:100%;padding:6px 8px;border:1px solid #D5DADD;border-radius:3px;font:inherit;background:#fff;color:var(--tinta)}
input:focus,select:focus,button:focus-visible,a:focus-visible{outline:2px solid var(--toska);outline-offset:1px}
input[type=radio],input[type=checkbox]{width:auto;accent-color:var(--pinus)}
.baris{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.baris-3{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
button,.tombol{display:inline-block;margin-top:12px;background:var(--toska);color:#fff;border:1px solid var(--toska);border-radius:3px;padding:7px 14px;font:inherit;font-size:13.5px;font-weight:500;cursor:pointer;text-decoration:none;line-height:1.4;box-shadow:0 1px 2px rgba(0,0,0,.12)}
button:hover,.tombol:hover{background:var(--toska-tua);border-color:var(--toska-tua)}
button:disabled{opacity:.5;cursor:not-allowed}
button.kecil,.tombol.kecil{margin:0;padding:3px 9px;font-size:12.5px}
button.garis,.tombol.garis{background:#fff;color:var(--pinus);border-color:var(--toska);box-shadow:none}
button.garis:hover,.tombol.garis:hover{background:var(--pinus-muda)}
button.bahaya{background:#fff;color:var(--merah);border-color:#DDA49F;box-shadow:none}
button.bahaya:hover{background:var(--merah-muda)}
.aksi{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
.aksi form{display:flex;gap:6px;align-items:center;margin:0}
.aksi select{width:auto;padding:3px 6px;font-size:13px}
.hasil{margin-bottom:16px;border-left:4px solid var(--toska)}
.hasil.gagal{border-left-color:var(--merah)}
pre{background:#F8F9FA;border:1px solid var(--garis);border-radius:3px;padding:10px;overflow:auto;font:12.5px/1.45 Consolas,"Liberation Mono",monospace;max-height:380px;margin:6px 0 10px;white-space:pre-wrap;word-break:break-word}
.kode{display:inline-block;padding:1px 8px;border-radius:10px;font-size:12.5px;background:var(--pinus-muda);color:var(--pinus)}
.gagal .kode{background:var(--merah-muda);color:var(--merah)}
.pesan-fp{background:var(--amber-muda);border:1px solid #E9C98C;color:var(--amber);border-radius:3px;padding:12px 14px;margin:10px 0}
.pesan-fp b{display:block;color:#6E4100}
.info{background:var(--pinus-muda);border-radius:3px;padding:10px 14px;margin-bottom:14px}
.peringatan{background:var(--amber-muda);color:#6E4100;border:1px solid #E9C98C;border-radius:3px;padding:10px 14px;margin-bottom:14px}
.galat{background:var(--merah-muda);color:var(--merah);border-radius:3px;padding:10px 14px;margin-bottom:14px}
table{width:100%;border-collapse:collapse;font-size:13.5px}
th,td{text-align:left;padding:6px 8px;border-bottom:1px solid var(--garis);vertical-align:middle}
th{color:var(--redup);font-weight:500;font-size:12.5px;background:#FAFAFA;position:sticky;top:0}
tr.redup td{color:var(--redup)}
td.angka,th.angka{text-align:right;font-variant-numeric:tabular-nums}
.no-antrean{font-weight:700;font-variant-numeric:tabular-nums;white-space:nowrap}
.gulir{overflow-x:auto}
.gulir-tinggi{overflow:auto;max-height:420px}
small.redup,.redup{color:var(--redup)}
.lencana{display:inline-block;padding:1px 8px;border-radius:10px;font-size:12px;font-weight:500;white-space:nowrap;background:var(--abu-muda);color:var(--redup)}
.lencana.checkin{background:var(--biru-muda);color:var(--biru)}
.lencana.dipanggil{background:var(--amber-muda);color:var(--amber)}
.lencana.dilayani{background:var(--pinus-muda);color:var(--pinus)}
.lencana.selesai{background:var(--pinus);color:#fff}
.lencana.batal{background:var(--merah-muda);color:var(--merah)}
.lencana.menunggu{background:var(--abu-muda);color:var(--redup)}
.lencana.fp{background:var(--amber-muda);color:#6E4100}
.lencana.ok{background:var(--pinus-muda);color:var(--pinus)}
.lencana.tidak{background:var(--merah-muda);color:var(--merah)}
.lencana.simulator,.lencana.internal{background:#EDE7F6;color:#5B3E96}
.statistik{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:14px}
.statistik div{background:var(--permukaan);border:1px solid var(--garis);border-radius:3px;padding:10px 14px}
.statistik b{display:block;font-size:26px;font-weight:500;line-height:1.2;font-variant-numeric:tabular-nums}
.statistik span{color:var(--redup);font-size:12.5px}
.statistik div.utama{border-color:var(--toska);background:var(--pinus-muda)}
.statistik div.utama b{color:var(--pinus)}
.filter{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap}
.filter > div{min-width:150px}
.filter button{margin-top:0}
.tab{display:flex;gap:4px;border-bottom:1px solid var(--garis);margin-bottom:14px}
.tab a{padding:7px 14px;text-decoration:none;color:var(--redup);border:1px solid transparent;border-bottom:0;border-radius:3px 3px 0 0;margin-bottom:-1px}
.tab a.aktif{background:var(--permukaan);border-color:var(--garis);color:var(--tinta);font-weight:500;box-shadow:inset 0 2px 0 var(--toska)}
.kartu-pasien{border:1px solid var(--garis);border-radius:3px;padding:10px 12px;background:#FAFAFA}
.kartu-pasien dl{display:grid;grid-template-columns:max-content 1fr;gap:2px 12px;margin:6px 0 0;font-size:13.5px}
.kartu-pasien dt{color:var(--redup)}
.kartu-pasien dd{margin:0}
.tiket{border:2px dashed var(--toska);border-radius:4px;padding:14px 18px;background:var(--permukaan);display:flex;gap:28px;align-items:center;flex-wrap:wrap;margin-bottom:14px}
.tiket .besar{font-size:30px;font-weight:700;color:var(--pinus);line-height:1.1;font-variant-numeric:tabular-nums}
.tiket span{display:block;color:var(--redup);font-size:12.5px}
details.bpjs{margin:-6px 0 14px}
details.bpjs summary{cursor:pointer;color:var(--redup);font-size:13px}
.pilihan-radio{display:flex;gap:14px;flex-wrap:wrap;margin-top:4px}
.pilihan-radio label{display:flex;gap:6px;align-items:center;margin:0;color:var(--tinta);font-size:14px}
a{color:var(--pinus)}
.nomor-panggil{font-size:40px;font-weight:700;color:var(--pinus);line-height:1}
/* Loading satu halaman: garis detak jantung (EKG) saat memproses data atau pindah halaman. */
.memuat{position:fixed;inset:0;z-index:100;display:flex;align-items:center;justify-content:center;background:rgba(247,247,247,.78);opacity:0;visibility:hidden;transition:opacity .2s ease,visibility 0s linear .2s}
.memuat.tampil{opacity:1;visibility:visible;transition:opacity .2s ease}
.memuat-kartu{background:#fff;border-radius:6px;box-shadow:0 6px 24px rgba(15,60,80,.18);padding:18px 26px 16px;text-align:center;min-width:260px}
.memuat-kartu svg{display:block;margin:0 auto}
.memuat-jantung{transform-origin:center;animation:degup 1.2s ease-in-out infinite}
.memuat-garis{stroke-dasharray:90 330;stroke-dashoffset:420;animation:ekg 1.6s linear infinite}
.memuat-teks{margin-top:6px;font-size:13.5px;color:var(--tinta)}
@keyframes ekg{to{stroke-dashoffset:0}}
@keyframes degup{0%,40%,100%{transform:scale(1)}10%{transform:scale(1.18)}20%{transform:scale(1.02)}30%{transform:scale(1.12)}}
@media (prefers-reduced-motion:reduce){*{transition:none!important}.memuat-jantung,.memuat-garis{animation:none}.memuat-garis{stroke-dasharray:none}}
</style>
@stack('gaya')
</head>
<body class="@yield('kelas_body')">
@php
  $logoRs = (string) config('antrol.logo');
  $adaLogo = $logoRs !== '' && is_file(public_path($logoRs));
  $bpjsDev = str_contains(strtolower((string) config('antrol.bpjs.base_url')), 'dev');
@endphp
<header class="kop">
  <div class="kop-dalam">
    <a class="kop-logo" href="{{ route('antrol.beranda') }}" aria-label="{{ config('antrol.nama_rs') }} - Daftar Antrian">
      @if ($adaLogo)
        <img src="{{ asset($logoRs) }}" alt="Logo {{ config('antrol.nama_rs') }}">
      @else
        {{-- Placeholder: simpan logo RS di public/{{ $logoRs }} untuk menggantinya. --}}
        <svg class="logo-placeholder" width="50" height="50" viewBox="0 0 50 50" role="img" aria-label="Logo RS (placeholder)">
          <rect x="1.5" y="1.5" width="47" height="47" rx="6" fill="#fff" stroke="#1F3A5F" stroke-width="2.5" stroke-dasharray="5 3"/>
          <text x="25" y="31" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" font-size="16" font-weight="700" fill="#1F3A5F">RS</text>
        </svg>
      @endif
    </a>
    <div class="kop-isi">
      <div class="kop-atas">
        <span class="kop-nama">{{ config('antrol.nama_rs') }}</span>
        <span class="kop-modul">Modul Antrean Online</span>
        <span class="kop-pengguna">@include('antrol.partials.ikon', ['nama' => 'pengguna', 'ukuran' => 13]) {{ auth()->user()?->name ?? 'Petugas' }}</span>
      </div>
      <div class="kop-bawah">
        <div class="kop-label" aria-label="Mode modul">
          <span class="label-mode {{ $bpjsDev ? 'dev' : 'prod' }}">BPJS {{ $bpjsDev ? 'Development' : 'Production' }}</span>
          @if (\App\Services\Antrol\PelayananService::alatSimulasi())
            <span class="label-simulasi" title="Pendaftaran (simulasi), Poli (simulasi), dan Data uji ditampilkan (ANTROL_TAMPILKAN_ALAT_SIMULASI=true)">ALAT SIMULASI AKTIF</span>
          @endif
          @if (\App\Services\Antrol\PelayananService::modeSimulasi())
            <span class="label-simulasi" title="Pendaftaran mengirim Tambah Antrean dan halaman Poli mengirim Update Waktu task ID 3, 4, 5 ke BPJS">MODE SIMULASI - DEVELOPMENT</span>
          @endif
        </div>
        <div class="kop-kanan">
          <span>@include('antrol.partials.ikon', ['nama' => 'kalender', 'ukuran' => 13]) {{ now()->locale('id')->translatedFormat('l, d F Y') }}</span>
        </div>
      </div>
    </div>
  </div>
</header>
<main>
  @unless (request()->routeIs('antrol.beranda'))
    <a class="tombol-kembali" href="{{ route('antrol.beranda') }}">@include('antrol.partials.ikon', ['nama' => 'kembali', 'ukuran' => 22]) <span>Daftar Antrian</span></a>
  @endunless
  @if (session('pesan'))<div class="info" role="status">{{ session('pesan') }}</div>@endif
  @if (session('peringatan'))<div class="peringatan" role="alert">{{ session('peringatan') }}</div>@endif
  @if ($errors->any())<div class="galat" role="alert">{{ implode(' ', $errors->all()) }}</div>@endif
  @if (session('bpjs'))
    <details class="bpjs">
      <summary>Lihat request &amp; response BPJS ({{ count(session('bpjs')) }})</summary>
      @foreach (session('bpjs') as $b)
        @include('antrol.partials.hasil', ['hasil' => $b, 'judul' => $b['method'] . ' ' . parse_url($b['url'] ?? '', PHP_URL_PATH)])
      @endforeach
    </details>
  @endif
  @yield('isi')
</main>
<div id="antrol-memuat" class="memuat" role="status" aria-live="polite" aria-hidden="true">
  <div class="memuat-kartu">
    <svg width="220" height="64" viewBox="0 0 220 64" aria-hidden="true" focusable="false">
      <path class="memuat-jantung" d="M110 18c-4-7-15-6-15 3 0 7 15 15 15 15s15-8 15-15c0-9-11-10-15-3z" fill="#EE5A50" opacity=".9"/>
      <path d="M2 44H62l6-14 8 26 8-34 6 22h40l6-12 8 22 7-28 6 18H218" fill="none" stroke="#DCEFE9" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>
      <path class="memuat-garis" d="M2 44H62l6-14 8 26 8-34 6 22h40l6-12 8 22 7-28 6 18H218" fill="none" stroke="#38BF9D" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>
    </svg>
    <div class="memuat-teks" id="antrol-memuat-teks">Memproses data...</div>
  </div>
</div>
<script>
// Loading satu halaman (detak jantung). window.antrolLoading.tampil(pesan) / .sembunyi().
// Muncul setelah jeda singkat agar proses cepat tidak berkedip.
window.antrolLoading = (function () {
  var el = document.getElementById('antrol-memuat');
  var teks = document.getElementById('antrol-memuat-teks');
  var tunda = null, batas = null;
  function sembunyi() {
    clearTimeout(tunda); clearTimeout(batas);
    el.classList.remove('tampil'); el.setAttribute('aria-hidden', 'true');
  }
  function tampil(pesan, batasMs) {
    clearTimeout(tunda); clearTimeout(batas);
    teks.textContent = pesan || 'Memproses data...';
    // Dialog modal berada di top layer: pindahkan loading ke dalam dialog yang terbuka agar tetap terlihat.
    var dialog = document.querySelector('dialog[open]');
    (dialog || document.body).appendChild(el);
    tunda = setTimeout(function () { el.classList.add('tampil'); el.setAttribute('aria-hidden', 'false'); }, 150);
    // Pengaman: jika halaman tidak berpindah (mis. unduhan), loading ditutup sendiri.
    if (batasMs) { batas = setTimeout(sembunyi, batasMs); }
  }
  return { tampil: tampil, sembunyi: sembunyi };
})();
// Konfirmasi sebelum kirim form yang memiliki data-konfirmasi.
document.addEventListener('submit', function (e) {
  var pesan = e.target.getAttribute('data-konfirmasi');
  if (pesan && !window.confirm(pesan)) { e.preventDefault(); return; }
  // Cegah klik ganda.
  var tombol = e.submitter;
  if (tombol && !tombol.hasAttribute('data-boleh-ulang') && !e.target.hasAttribute('data-boleh-ulang')) {
    setTimeout(function () { tombol.disabled = true; tombol.setAttribute('data-sedang-kirim', ''); }, 0);
  }
  // Form yang dikirim lewat fetch (preventDefault) mengatur loading-nya sendiri.
  if (!e.defaultPrevented && e.target.target !== '_blank' && !e.target.hasAttribute('data-tanpa-memuat')) {
    window.antrolLoading.tampil(e.target.getAttribute('data-pesan-memuat'));
  }
});
// Loading saat pindah halaman lewat tautan biasa.
document.addEventListener('click', function (e) {
  var a = e.target.closest('a[href]');
  if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
  if ((a.target && a.target !== '_self') || a.hasAttribute('download') || a.hasAttribute('data-tanpa-memuat')) { return; }
  var href = a.getAttribute('href');
  if (!href || href.charAt(0) === '#' || /^(javascript|mailto|tel):/i.test(href) || a.origin !== location.origin) { return; }
  if (a.pathname === location.pathname && a.search === location.search && a.hash) { return; }
  window.antrolLoading.tampil('Membuka halaman...', 15000);
});
// Halaman dibuka ulang dari riwayat browser: tutup loading dan aktifkan lagi tombol.
window.addEventListener('pageshow', function () {
  window.antrolLoading.sembunyi();
  document.querySelectorAll('[data-sedang-kirim]').forEach(function (b) { b.disabled = false; b.removeAttribute('data-sedang-kirim'); });
});
// Tombol buka/tutup elemen lain: data-buka="id".
document.addEventListener('click', function (e) {
  var t = e.target.closest('[data-buka]');
  if (!t) return;
  var el = document.getElementById(t.getAttribute('data-buka'));
  if (!el) return;
  el.hidden = !el.hidden;
  t.setAttribute('aria-expanded', String(!el.hidden));
});
</script>
@stack('skrip')
</body>
</html>
