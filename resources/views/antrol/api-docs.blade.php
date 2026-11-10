@extends('antrol.layout')
@section('judul', 'Dokumentasi API')
@push('gaya')
<link rel="stylesheet" href="{{ $css }}">
<style>
#swagger-ui{background:#fff;border:1px solid var(--garis);border-radius:3px;padding:4px 0 10px}
#swagger-ui .topbar{display:none}
/* Netralkan gaya global layout (kelas .info pesan flash, pre, button, tabel, form) di dalam Swagger UI. */
#swagger-ui .info{background:none;border-radius:0;padding:0;margin:18px 0}
#swagger-ui button{margin-top:0;box-shadow:none;font-weight:inherit;line-height:inherit}
#swagger-ui pre{max-height:none;border:0;margin:0;white-space:pre-wrap}
#swagger-ui pre.version{background:#7d8492;color:#fff;border-radius:57px;padding:2px 4px;font-size:10px;font-family:sans-serif}
#swagger-ui small pre.version{display:inline-block}
#swagger-ui .info .title small.version-stamp pre.version{background:#89bf04}
#swagger-ui table th{position:static;background:none}
#swagger-ui input,#swagger-ui select{width:auto}
#swagger-ui label{display:inline;margin:0;color:inherit;font-size:inherit}
</style>
@endpush
@section('isi')
<div class="kepala">
  <div>
    <h1>Dokumentasi API</h1>
    <p class="sub">API untuk kiriman dari SIMRS dan WSRS untuk BPJS (OpenAPI 3.0). Kunci API dan token tidak disimpan di halaman ini.</p>
  </div>
  <a class="tombol garis" href="{{ route('antrol.api-docs.yaml') }}" download="openapi.yaml">Unduh openapi.yaml</a>
</div>
<div id="swagger-ui"><p class="redup" style="padding:14px">Memuat Swagger UI... Jika tidak muncul, server tidak dapat mengakses CDN; unduh openapi.yaml dengan tombol di atas.</p></div>
@endsection
@push('skrip')
<script src="{{ $js }}"></script>
<script>
if (window.SwaggerUIBundle) {
  window.SwaggerUIBundle({ url: @json(route('antrol.api-docs.yaml')), dom_id: '#swagger-ui', deepLinking: true, docExpansion: 'list', defaultModelsExpandDepth: 0 });
}
</script>
@endpush
