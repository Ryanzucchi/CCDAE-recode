@extends('layouts.ccdae')
@section('title', $title)
@section('content')
<div class="ccdae-page-heading"><div><p class="ccdae-breadcrumb">{{ $title }} / Visão geral</p><h1>{{ $title }}</h1></div></div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px;margin:24px 0">
@foreach($stats as $label => $value)<section class="ccdae-map-card ccdae-module-stat" data-module="{{ $module }}" @if($label === 'Incidentes em aberto' && $value > 0) data-alert="true" @endif><h2>{{ $label }}</h2><p class="ccdae-dashboard-number">{{ $value === null ? 'Indisponível' : number_format($value, 0, ',', '.') }}</p><p>{{ $module === 'transito' ? (['Vias monitoradas' => 'Malha viária cadastrada', 'Veículos na última leitura' => $readingTime ?? 'Sem leitura disponível', 'Infrações em 30 dias' => 'Registros recentes'][$label] ?? '') : ($districtId ? 'No distrito selecionado' : 'Total da cidade') }}</p></section>@endforeach
</div>
@if($map)
<section class="ccdae-map-card ccdae-network-card"><h2>Visão geral da infraestrutura {{ $module === 'energia' ? 'elétrica' : 'de internet' }}</h2><p>{{ $module === 'energia' ? 'Clique em um fio, poste, transformador ou central para consultar seus dados.' : 'Clique em uma fibra, antena ou equipamento para consultar seus dados.' }}</p>
<form class="ccdae-search ccdae-network-filter" method="get">
    <label for="district">Distrito</label><select name="distrito_id" id="district" onchange="this.form.requestSubmit()"><option value="">Visão geral da cidade</option>@foreach($districts as $district)<option value="{{ $district->id }}" @selected($districtId === $district->id)>{{ $district->nome }}</option>@endforeach</select>
    <label for="engine">Mapa</label><select name="engine" id="engine" onchange="this.form.requestSubmit()"><option value="maplibre" @selected(request('engine') !== 'leaflet')>MapLibre</option><option value="leaflet" @selected(request('engine') === 'leaflet')>Leaflet</option></select>
    <noscript><button type="submit" class="ccdae-theme-toggle">Aplicar</button></noscript><small>A visão geral mostra toda a rede de {{ $module === 'energia' ? 'energia elétrica' : 'telecomunicações' }}. O distrito apenas filtra a visualização.</small>
</form>
<div class="ccdae-network-map"><x-geoveil::map :map="$map" /><aside class="ccdae-network-legend"><strong>Rede de {{ $module === 'energia' ? 'energia elétrica' : 'internet' }}</strong>
@if($module === 'energia')<span>● Postes</span><span>━ Baixa tensão</span><span>━ Alta tensão</span><span>● Equipamentos e centrais</span>
@else<span>━ Fibra óptica</span><span>━ Backbone</span><span>━ Cobre</span><span>● Antenas</span><span>● Equipamentos</span>@endif
</aside></div></section>
@foreach($notices as $notice)<p role="status" class="ccdae-map-card">{{ $notice }}</p>@endforeach
@endif
@if($module === 'transito')<p><a class="ccdae-theme-toggle" href="{{ route('module.intensity', ['module' => 'transito', 'type' => 'infracoes']) }}">Mapa de infrações</a></p>@endif
@if($module === 'transito')<p><a class="ccdae-theme-toggle" href="{{ route('module.intensity', ['module' => 'transito', 'type' => 'volume']) }}">Volume de veículos</a> <a class="ccdae-theme-toggle" href="{{ route('module.intensity', ['module' => 'transito', 'type' => 'acidentes']) }}">Mapa de acidentes</a> <a class="ccdae-theme-toggle" href="{{ route('module.intensity', ['module' => 'transito', 'type' => 'obras']) }}">Mapa de obras</a></p>@endif
@if($module === 'energia')<p><a class="ccdae-theme-toggle" href="{{ route('module.intensity', ['module' => 'energia', 'type' => 'densidade']) }}">Densidade da rede elétrica</a></p>@endif
@if($module === 'energia')<p><a class="ccdae-theme-toggle" href="{{ route('module.intensity', ['module' => 'energia', 'type' => 'consumo']) }}">Consumo médio estimado</a> <a class="ccdae-theme-toggle" href="{{ route('module.intensity', ['module' => 'energia', 'type' => 'populacao']) }}">População</a></p>@endif
@if($module === 'internet')<p><a class="ccdae-theme-toggle" href="{{ route('module.intensity', ['module' => 'internet', 'type' => 'densidade-rede']) }}">Densidade da rede de internet</a></p>@endif
@if($module === 'internet')<p><a class="ccdae-theme-toggle" href="{{ route('module.intensity', ['module' => 'internet', 'type' => 'demanda']) }}">Demanda potencial de internet</a></p>@endif
@if($module === 'transito')<p><a class="ccdae-theme-toggle" href="{{ route('traffic.infractions.index') }}">Infrações de trânsito</a></p>@endif
@if($module === 'internet')<p><a class="ccdae-theme-toggle" href="{{ route('internet.incidents.index') }}">Incidentes de internet</a></p>@endif
@if($module === 'energia')<p><a class="ccdae-theme-toggle" href="{{ route('energy.incidents.index') }}">Incidentes de energia</a></p>@endif
<a class="ccdae-theme-toggle" href="{{ route('maps.analytic', ['page' => $module === 'transito' ? 'transito' : 'infraestrutura', 'distrito_id' => $districtId ?: null]) }}">{{ $module === 'transito' ? 'Abrir mapa de trânsito' : 'Abrir mapa de infraestrutura' }}</a>
@endsection
