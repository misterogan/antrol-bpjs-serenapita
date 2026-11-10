@php
  $labelStatus = [
      'terdaftar' => 'Terdaftar',
      'checkin'   => 'Sudah check in',
      'dipanggil' => 'Dipanggil',
      'dilayani'  => 'Sedang dilayani',
      'selesai'   => 'Selesai',
      'batal'     => 'Batal',
      'menunggu'  => 'Menunggu',
  ];
@endphp
<span class="lencana {{ $status }}">{{ $labelStatus[$status] ?? $status }}</span>
