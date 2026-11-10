{{--
  Ikon bulat kartu Daftar Antrian: lingkaran bergradasi biru ke hijau, lingkaran luar hijau tipis,
  dan gambar garis tebal di tengah. Digambar sendiri untuk modul ini (bukan aset pihak lain).
  @include('antrol.partials.ikon-kartu', ['nama' => 'farmasi'])
--}}
@php
  $id = 'g'.substr(md5($nama.uniqid('', true)), 0, 8);
  $navi = '#233E66'; $isi = '#9FD9D3'; $merah = '#EE5A50';
@endphp
<svg class="ikon-kartu" width="170" height="170" viewBox="0 0 170 170" aria-hidden="true" focusable="false">
  <defs>
    <linearGradient id="{{ $id }}" x1="0" y1="1" x2="1" y2="0">
      <stop offset="0" stop-color="#1388B5"/><stop offset="1" stop-color="#74C46A"/>
    </linearGradient>
    <filter id="{{ $id }}b" x="-20%" y="-20%" width="140%" height="140%"><feDropShadow dx="0" dy="3" stdDeviation="3" flood-color="#0B3A4F" flood-opacity=".28"/></filter>
  </defs>
  <circle cx="85" cy="85" r="82" fill="none" stroke="#5EC29B" stroke-width="1.6"/>
  <circle cx="85" cy="85" r="72" fill="url(#{{ $id }})" filter="url(#{{ $id }}b)"/>
  <g stroke="{{ $navi }}" stroke-width="3.5" stroke-linejoin="round" stroke-linecap="round">
  @switch($nama)
    @case('pendaftaran')
      <rect x="80" y="56" width="36" height="52" rx="4" fill="{{ $isi }}"/>
      <rect x="89" y="50" width="18" height="10" rx="2.5" fill="{{ $merah }}" stroke-width="3"/>
      <path d="M88 74h20M88 83h20M88 92h12" stroke-width="3" fill="none"/>
      <circle cx="64" cy="70" r="9" fill="{{ $isi }}"/>
      <path d="M48 116v-15a16 16 0 0 1 32 0v15z" fill="{{ $isi }}"/>
      <path d="M58 96v14" stroke="#fff" stroke-width="3"/>
      @break
    @case('farmasi')
      <rect x="54" y="68" width="32" height="46" rx="5" fill="{{ $isi }}"/>
      <rect x="57" y="56" width="26" height="12" rx="2.5" fill="{{ $merah }}"/>
      <rect x="61" y="80" width="18" height="16" rx="1.5" fill="#fff" stroke-width="2.5"/>
      <path d="M70 84v8M66 88h8" stroke-width="2.5"/>
      <g transform="rotate(35 104 92)">
        <rect x="95.5" y="68" width="17" height="46" rx="8.5" fill="{{ $isi }}"/>
        <path d="M95.5 91V76.5a8.5 8.5 0 0 1 17 0V91z" fill="{{ $merah }}"/>
      </g>
      @break
    @case('log')
      <rect x="54" y="48" width="48" height="68" rx="5" fill="{{ $isi }}"/>
      <path d="M64 64h28M64 74h28M64 84h18" stroke-width="3" fill="none"/>
      <circle cx="104" cy="102" r="17" fill="#fff"/>
      <path d="M95 97h17l-4-4" stroke="{{ $merah }}" stroke-width="3" fill="none"/>
      <path d="M113 107H96l4 4" stroke-width="3" fill="none"/>
      @break
    @case('alat')
      <path d="M76 50v22l-15 28a6 6 0 0 0 5.3 9h37.4a6 6 0 0 0 5.3-9L94 72V50z" fill="{{ $isi }}"/>
      <path d="M70 50h30" fill="none"/>
      <path d="M65.5 92h39l3.7 7a3 3 0 0 1-2.7 4.4h-41a3 3 0 0 1-2.7-4.4z" fill="{{ $merah }}" stroke-width="2.5"/>
      <circle cx="82" cy="82" r="3" fill="#fff" stroke-width="2.5"/>
      <circle cx="90" cy="74" r="2" fill="#fff" stroke-width="2"/>
      @break
    @case('dokumen')
      <path d="M58 52a4 4 0 0 1 4-4h34l16 16v52a4 4 0 0 1-4 4H62a4 4 0 0 1-4-4z" fill="{{ $isi }}"/>
      <path d="M96 48v16h16" fill="#fff"/>
      <path d="M76 80l-9 9 9 9M94 80l9 9-9 9" stroke-width="3" fill="none"/>
      <path d="M89 76l-8 26" stroke="{{ $merah }}" stroke-width="3" fill="none"/>
      @break
    @case('monitoring')
      <rect x="49" y="54" width="72" height="50" rx="4" fill="{{ $isi }}"/>
      <path d="M78 104v10M92 104v10M70 116h30" fill="none"/>
      <rect x="59" y="82" width="9" height="14" fill="#fff" stroke-width="2.5"/>
      <rect x="73" y="73" width="9" height="23" fill="{{ $merah }}" stroke-width="2.5"/>
      <rect x="87" y="66" width="9" height="30" fill="#fff" stroke-width="2.5"/>
      <rect x="101" y="77" width="9" height="19" fill="#fff" stroke-width="2.5"/>
      @break
  @endswitch
  </g>
</svg>
