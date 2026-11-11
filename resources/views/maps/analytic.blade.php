@extends('layouts.ccdae')
@section('title', $title)
@section('content')
<div class="ccdae-page-heading"><div><p class="ccdae-breadcrumb">Mapas / {{ ucfirst($page) }}</p><h1>{{ $title }}</h1></div></div>
<form method="get" class="ccdae-search ccdae-map-card">
    <label for="district">Distrito</label><select id="district" name="distrito_id"><option value="">Todos os distritos</option>@foreach($districts as $district)<option value="{{ $district->id }}" @selected($districtId === $district->id)>{{ $district->nome }}</option>@endforeach</select>
    <label for="engine">Mapa</label><select id="engine" name="engine"><option value="maplibre" @selected(request('engine') !== 'leaflet')>MapLibre</option><option value="leaflet" @selected(request('engine') === 'leaflet')>Leaflet</option></select>
    @if($page === 'clima')
    <label for="metric">Variável climática</label><select id="metric" name="metric">@foreach(\App\Http\Controllers\Map\AnalyticMapController::METRICS as $key => $definition)<option value="{{ $key }}" @selected($metric === $key)>{{ $definition[2] }} {{ $definition[3] }}</option>@endforeach</select>
    @endif
    <button class="ccdae-theme-toggle" type="submit">Aplicar filtros</button><a href="{{ url()->current() }}">Limpar</a>
</form>
@foreach($notices as $notice)<p role="status" class="ccdae-map-card">{{ $notice }}</p>@endforeach
@if($page === 'transito')<p class="ccdae-map-card">Velocidade: vermelho &lt; 10 km/h · laranja 10–20 · amarelo 20–40 · verde ≥ 40. Cinza: sem leitura.</p>@endif
@if($page === 'infraestrutura')
<div class="ccdae-search ccdae-map-card" data-infrastructure-switch data-scene-url="{{ route('maps.infrastructure-scene') }}">
    <button type="button" class="ccdae-theme-toggle" data-infrastructure-mode="2d" aria-pressed="true">Mapa 2D</button>
    <button type="button" class="ccdae-theme-toggle" data-infrastructure-mode="3d" aria-pressed="false">Visualizador 3D</button>
    <label><input type="checkbox" data-infrastructure-buildings checked> Construções 3D</label>
</div>
@endif
<section class="ccdae-map-card" @if($page === 'infraestrutura') data-infrastructure-map @endif><x-geoveil::map :map="$map" /></section>
@if($page === 'infraestrutura')
<section class="ccdae-map-card ccdae-infrastructure-scene" data-infrastructure-scene hidden>
    <div data-infrastructure-warning class="ccdae-scene-warning" hidden><h2>Visualizador 3D requer um distrito com postes</h2><p>Selecione um distrito com postes no filtro acima para mostrar a maquete de postes, cabos e equipamentos.</p></div>
    <div id="infra-3d-container" data-buildings-url="{{ route('maps.building-viewport') }}" data-terrain-enabled="{{ config('maps.terrain.enabled') ? 'true' : 'false' }}" data-terrain-tile-url="{{ config('maps.terrain.terrarium_tile_url') }}" data-terrain-zoom="{{ config('maps.terrain.zoom') }}" data-terrain-exaggeration="{{ config('maps.terrain.exaggeration') }}"></div>
    <div id="three-tooltip" hidden></div>
    <p class="ccdae-scene-status" data-infrastructure-status role="status"></p>
    <aside class="ccdae-scene-legend"><strong>Modelo físico da rede</strong><span>◯ Postes: altura e material cadastrados</span><span>● Luminária instalada</span><span>━ Fibra óptica</span><span>━ Rede elétrica / estado crítico</span><span>▲ Vegetação contextual (ilustrativa)</span><span>▰ Ruas e fluxo de trânsito</span><span>Relevo: {{ config('maps.terrain.attribution') }}</span></aside>
</section>
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js" defer></script>
@endif
@if(count($times))
<form method="get" class="ccdae-search ccdae-map-card" style="margin-top:16px" @if($page === 'transito') data-traffic-timeline @endif>
    <input type="hidden" name="metric" value="{{ $metric }}"><input type="hidden" name="engine" value="{{ request('engine', 'maplibre') }}"><input type="hidden" name="distrito_id" value="{{ $districtId ?: '' }}">
    <label for="at">Histórico · {{ $page === 'transito' ? 'últimas 24 horas com leituras' : 'últimas 24 leituras em 24 horas' }}</label><select id="at" name="at">@if($page === 'transito')<option value="">Situação atual</option>@endif @foreach($times as $time)<option value="{{ $time }}" @selected($time === $selectedTime)>{{ \Carbon\Carbon::parse($time)->format('d/m H:i') }}</option>@endforeach</select><button class="ccdae-theme-toggle" type="submit">Ver leitura</button>
    @if($page === 'transito')<button type="button" class="ccdae-theme-toggle" data-traffic-play aria-label="Reproduzir histórico">▶ Reproduzir</button><input type="range" data-traffic-range min="0" max="{{ count($times) }}" value="{{ $selectedTime ? array_search($selectedTime, array_reverse($times), true) : count($times) }}" aria-label="Horário no histórico"><output data-traffic-status role="status">{{ $selectedTime ? \Carbon\Carbon::parse($selectedTime)->format('d/m/Y H:i') : 'Situação atual' }}</output>@endif
</form>
@endif
@endsection
