{{--
  Ikon garis kecil buatan modul (SVG inline, warna mengikuti currentColor).
  @include('antrol.partials.ikon', ['nama' => 'poli', 'ukuran' => 14])
--}}
@php $ukuran = $ukuran ?? 14; @endphp
<svg class="ikon" width="{{ $ukuran }}" height="{{ $ukuran }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
@switch($nama)
  @case('pendaftaran')
    <rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V3h6v1"/><path d="M9 10h6M9 14h6M9 18h3"/>
    @break
  @case('poli')
    <path d="M6 3v6a4 4 0 0 0 8 0V3"/><path d="M10 13v2a5 5 0 0 0 10 0v-2"/><circle cx="20" cy="11" r="2"/>
    @break
  @case('farmasi')
    <path d="M10.5 3.5l10 10a4.95 4.95 0 0 1-7 7l-10-10a4.95 4.95 0 0 1 7-7z"/><path d="M8.5 13.5l5-5"/>
    @break
  @case('monitoring')
    <rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M8 20h8M12 16v4"/><path d="M7 12l3-3 2 2 4-4"/>
    @break
  @case('demo')
    <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M17.5 14v7M14 17.5h7"/>
    @break
  @case('log')
    <path d="M8 6h13M8 12h13M8 18h13"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>
    @break
  @case('pengguna')
    <circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>
    @break
  @case('kalender')
    <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>
    @break
  @case('kiri')
    <path d="M15 5l-7 7 7 7"/>
    @break
  @case('kanan')
    <path d="M9 5l7 7-7 7"/>
    @break
  @case('kembali')
    <path d="M20 12H5M11 5l-7 7 7 7"/>
    @break
  @case('ponsel')
    <rect x="6" y="2" width="12" height="20" rx="2.5"/><path d="M11 18h2"/>
    @break
@endswitch
</svg>
