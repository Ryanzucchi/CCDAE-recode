@props(['map'])
<section {{ $attributes->class(['gv-workspace']) }} data-geoveil>
    <script type="application/json" data-geoveil-config>{!! json_encode($map->toArray(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
    <p class="gv-loading" role="status">Carregando mapa…</p>
</section>
