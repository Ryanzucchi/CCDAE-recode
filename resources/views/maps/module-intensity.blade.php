@extends('layouts.ccdae')
@section('title', $title)
@section('content')
<div class="ccdae-page-heading"><div><p class="ccdae-breadcrumb"><a href="{{ route($module) }}">{{ ucfirst($module) }}</a> / Mapas</p><h1>{{ $title }}</h1></div></div>
<form method="get" class="ccdae-search ccdae-map-card">
    <label for="district">Distrito</label><select id="district" name="distrito_id"><option value="">Todos os distritos</option>@foreach($districts as $district)<option value="{{ $district->id }}" @selected($districtId === $district->id)>{{ $district->nome }}</option>@endforeach</select>
    <label for="engine">Mapa</label><select id="engine" name="engine"><option value="maplibre" @selected(request('engine') !== 'leaflet')>MapLibre 3D</option><option value="leaflet" @selected(request('engine') === 'leaflet')>Leaflet 2D</option></select>
    @if($module === 'transito')<label for="inicio">Início</label><input id="inicio" type="datetime-local" name="inicio" value="{{ $start->format('Y-m-d\TH:i') }}"><label for="fim">Fim</label><input id="fim" type="datetime-local" name="fim" value="{{ $end->format('Y-m-d\TH:i') }}">@endif
    <button class="ccdae-theme-toggle" type="submit">Aplicar filtros</button>
</form>
@if($errors->any())<p role="alert" class="ccdae-map-card">{{ $errors->first() }}</p>@endif
@foreach($notices as $notice)<p role="status" class="ccdae-map-card">{{ $notice }}</p>@endforeach
<section class="ccdae-map-card"><x-geoveil::map :map="$map" /></section>
@endsection
