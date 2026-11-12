@extends('layouts.ccdae')
@section('title', $title)
@section('content')
<div class="ccdae-page-heading"><div><p class="ccdae-breadcrumb">Mapas / {{ ucfirst($page) }}</p><h1>{{ $title }}</h1></div></div>
<form method="get" class="ccdae-search ccdae-map-card">
    <label for="district">Distrito</label><select id="district" name="distrito_id"><option value="">Todos os distritos</option>@foreach($districts as $district)<option value="{{ $district->id }}" @selected($districtId === $district->id)>{{ $district->nome }}</option>@endforeach</select>
    <label for="engine">Mapa</label><select id="engine" name="engine"><option value="maplibre" @selected(request('engine') !== 'leaflet')>MapLibre</option><option value="leaflet" @selected(request('engine') === 'leaflet')>Leaflet</option></select>
    @if($page === 'clima')<input type="hidden" name="metric" value="{{ $metric }}">@endif
    <button class="ccdae-theme-toggle" type="submit">Aplicar filtros</button><a href="{{ url()->current() }}">Limpar</a>
</form>
@foreach($notices as $notice)<p role="status" class="ccdae-map-card">{{ $notice }}</p>@endforeach
<section class="ccdae-map-card ccdae-analytic-map @if($page === 'infraestrutura') ccdae-infrastructure-stage @endif" @if($page === 'infraestrutura') data-infrastructure-switch data-scene-url="{{ route('maps.infrastructure-scene') }}" @endif>
    @if($page === 'infraestrutura')
    <div class="ccdae-infrastructure-controls">
        <div class="ccdae-infrastructure-modes" aria-label="Modo do mapa de infraestrutura">
            <button type="button" data-infrastructure-mode="2d" aria-pressed="true">Mapa 2D</button>
            <button type="button" data-infrastructure-mode="3d" aria-pressed="false">Visualizador 3D</button>
        </div>
        <label><input type="checkbox" data-infrastructure-buildings checked> Construções 3D</label>
    </div>
    <div data-infrastructure-map class="ccdae-infrastructure-2d">
        <p class="ccdae-infrastructure-zoom">Aproxime o zoom (nível 15+) para ver postes, cabos e equipamentos</p>
        <aside class="ccdae-infrastructure-legend" aria-label="Legenda de infraestrutura">
            <strong>Mapeamento de Infraestrutura</strong>
            <span><i style="background:#f59e0b"></i>Centrais de Distribuição</span><span><i style="background:#9ca3af"></i>Postes</span>
            <span><i style="background:#ec4899"></i>Antenas Telecom</span><span><i style="background:#10b981"></i>Equipamentos</span>
            <span><i style="background:#78716c"></i>Construções 3D · OSM + GBA ODbL</span>
            <strong>Fluxo de Trânsito</strong><span><i style="background:#22c55e"></i>Via livre</span><span><i style="background:#eab308"></i>Moderado</span><span><i style="background:#f97316"></i>Intenso</span><span><i style="background:#ef4444"></i>Parado</span>
            <strong>Cabeamento</strong><span><i style="background:#06b6d4"></i>Fibra óptica</span><span><i style="background:#facc15"></i>Fibra backbone</span><span><i style="background:#ef4444"></i>Alta tensão</span><span><i style="background:#3b82f6"></i>Outros cabeamentos</span>
        </aside>
    @endif
    @if($page === 'transito')
    <aside class="ccdae-traffic-legend" aria-label="Legenda do fluxo de trânsito">
        <strong>Fluxo de Trânsito</strong>
        <span><i style="background:#22c55e"></i>Via livre (&gt; 40 km/h)</span>
        <span><i style="background:#eab308"></i>Moderado (20–40 km/h)</span>
        <span><i style="background:#f97316"></i>Intenso (10–20 km/h)</span>
        <span><i style="background:#ef4444"></i>Parado (&lt; 10 km/h)</span>
        <span>→ fluxo da via · ↔ mão dupla</span>
        <span><i style="background:#6b7280"></i>Sem leitura / fronteira</span>
    </aside>
    @endif
    @if($page === 'clima')
    <nav class="ccdae-climate-controls" aria-label="Variáveis climáticas">
        <strong>Variáveis climáticas</strong>
        @foreach(['temperatura' => 'Temperatura (°C)', 'radiacao' => 'Radiação solar (W/m²)', 'vento' => 'Fluxo de ventos', 'chuva' => 'Precipitação (mm)', 'particulas' => 'Qualidade do ar (PM2.5)', 'pressao' => 'Pressão atmosférica (hPa)', 'uv' => 'Índice UV'] as $key => $label)
        <a href="{{ url()->current().'?'.http_build_query(array_filter(['metric' => $key, 'distrito_id' => $districtId ?: null, 'engine' => request('engine', 'maplibre')])) }}" @if($metric === $key) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <aside class="ccdae-climate-legend" aria-label="Legenda climática">
        <strong>{{ \App\Http\Controllers\Map\AnalyticMapController::METRICS[$metric][2] }} {{ \App\Http\Controllers\Map\AnalyticMapController::METRICS[$metric][3] }}</strong>
        @php($breaks = \App\Http\Controllers\Map\AnalyticMapController::METRICS[$metric][4])
        @foreach(['#38bdf8' => '≤ '.$breaks[0], '#22c55e' => $breaks[0].'–'.$breaks[1], '#facc15' => $breaks[1].'–'.$breaks[2], '#ef4444' => '> '.$breaks[2]] as $color => $label)<span><i style="background:{{ $color }}"></i>{{ $label }}</span>@endforeach
    </aside>
    @if($metric === 'vento')
    <aside class="ccdae-wind-summary" data-wind-summary aria-label="Campo de vento" @if(!$windSummary) hidden @endif>
        <small>Campo de vento</small>
        <div><span data-wind-arrow style="transform:rotate({{ $windSummary['direction'] ?? 0 }}deg)">↑</span><strong data-wind-speed>{{ $windSummary['speed'] ?? 0 }} km/h</strong></div>
        <span data-wind-description>{{ $windSummary ? $windSummary['cardinal'].' · '.$windSummary['description'] : '' }}</span>
        <p>As setas mostram direção e intensidade por distrito.</p>
    </aside>
    @endif
    @endif
    <x-geoveil::map :map="$map" />
    @if($page === 'infraestrutura')</div>@endif
@if($page === 'infraestrutura')
<div class="ccdae-infrastructure-scene" data-infrastructure-scene hidden>
    <div data-infrastructure-warning class="ccdae-scene-warning" hidden><h2>Visualizador 3D requer um distrito com postes</h2><p>Selecione um distrito com postes no filtro acima para mostrar a maquete de postes, cabos e equipamentos.</p></div>
    <div id="infra-3d-container" data-buildings-url="{{ route('maps.building-viewport') }}" data-terrain-enabled="{{ config('maps.terrain.enabled') ? 'true' : 'false' }}" data-terrain-tile-url="{{ config('maps.terrain.terrarium_tile_url') }}" data-terrain-zoom="{{ config('maps.terrain.zoom') }}" data-terrain-exaggeration="{{ config('maps.terrain.exaggeration') }}"></div>
    <div id="three-tooltip" hidden></div>
    <p class="ccdae-scene-status" data-infrastructure-status role="status"></p>
    <aside class="ccdae-scene-legend"><strong>Modelo físico da rede</strong><span>◯ Postes: altura e material cadastrados</span><span>● Luminária instalada</span><span>━ Fibra óptica</span><span>━ Rede elétrica / estado crítico</span><span>▲ Vegetação contextual (ilustrativa)</span><span>▰ Ruas e fluxo de trânsito</span><span>Relevo: {{ config('maps.terrain.attribution') }}</span></aside>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js" defer></script>
@endif
</section>
@if(count($times))
<form method="get" class="ccdae-search ccdae-map-card ccdae-analytic-timeline" style="margin-top:16px" @if($page === 'transito') data-traffic-timeline @elseif($page === 'clima') data-climate-timeline @endif>
    <input type="hidden" name="metric" value="{{ $metric }}"><input type="hidden" name="engine" value="{{ request('engine', 'maplibre') }}"><input type="hidden" name="distrito_id" value="{{ $districtId ?: '' }}">
    <label for="at">Histórico · {{ $page === 'transito' ? 'últimas 24 horas com leituras' : 'últimas 24 leituras em 24 horas' }}</label><select id="at" name="at">@if($page === 'transito')<option value="">Situação atual</option>@endif @foreach($times as $time)<option value="{{ $time }}" @selected($time === $selectedTime)>{{ \Carbon\Carbon::parse($time)->format('d/m H:i') }}</option>@endforeach</select><button class="ccdae-theme-toggle" type="submit">Ver leitura</button>
    @if($page === 'transito')<button type="button" class="ccdae-theme-toggle" data-traffic-play aria-label="Reproduzir histórico">▶ Reproduzir</button><input type="range" data-traffic-range min="0" max="{{ count($times) }}" value="{{ $selectedTime ? array_search($selectedTime, array_reverse($times), true) : count($times) }}" aria-label="Horário no histórico"><output data-traffic-status role="status">{{ $selectedTime ? \Carbon\Carbon::parse($selectedTime)->format('d/m/Y H:i') : 'Situação atual' }}</output>@endif
    @if($page === 'clima')<button type="button" class="ccdae-theme-toggle" data-climate-play aria-label="Reproduzir histórico climático" @disabled(count($times) < 2)>▶ Reproduzir</button><input type="range" data-climate-range min="0" max="{{ max(count($times) - 1, 0) }}" value="{{ array_search($selectedTime, array_reverse($times), true) ?: 0 }}" aria-label="Horário da leitura climática" @disabled(count($times) < 2)><output data-climate-status role="status">{{ $selectedTime ? \Carbon\Carbon::parse($selectedTime)->format('d/m/Y H:i') : 'Sem histórico' }}</output>@endif
</form>
@elseif($page === 'transito' || $page === 'clima')
<section class="ccdae-map-card ccdae-analytic-timeline ccdae-analytic-timeline-empty" aria-label="Histórico de {{ $page === 'transito' ? 'trânsito' : 'clima' }}" style="margin-top:16px">
    <button class="ccdae-theme-toggle" type="button" disabled>▶ Reproduzir</button>
    <strong>Histórico de {{ $page === 'transito' ? 'trânsito' : 'clima' }}</strong>
    <span>Leituras das últimas 24 horas</span>
    <input type="range" min="0" max="0" value="0" disabled aria-label="Horário da leitura">
    <output>Sem histórico</output>
</section>
@endif
@endsection
