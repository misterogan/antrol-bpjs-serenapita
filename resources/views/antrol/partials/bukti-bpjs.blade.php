{{-- Blok lipat "Lihat request & response BPJS" memakai partial hasil (request, respons terenkripsi, hasil dekripsi). --}}
<details class="bpjs" style="margin:12px 0 0">
  <summary>Lihat request &amp; response BPJS</summary>
  @include('antrol.partials.hasil', ['hasil' => $hasil, 'judul' => $judul])
</details>
