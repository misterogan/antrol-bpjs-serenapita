{{--
  Pakai di layar pendaftaran SIMRS:
  @include('antrol.partials.pesan-fingerprint', ['kodepoli' => $kodepoli])
  Pesan muncul jika poli tujuan termasuk poli fingerprint (setting HFIS).
--}}
@php
  $wajibFp = $wajibFp ?? (isset($kodepoli)
      ? app(\App\Services\Antrol\WsbpjsService::class)->poliWajibFingerprint($kodepoli)
      : false);
@endphp
@if ($wajibFp)
  <div class="pesan-fp" role="alert">
    <b>Poli fingerprint</b>
    {{ config('antrol.pesan_fingerprint') }}
  </div>
@endif
