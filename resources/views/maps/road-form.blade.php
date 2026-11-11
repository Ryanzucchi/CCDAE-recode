@extends('layouts.ccdae')
@section('title', $road ? 'Editar via' : 'Nova via')
@section('content')
@php($roadRoute = request()->routeIs('original-roads.*') ? 'original-roads.' : 'roads.')
<div class="ccdae-page-heading"><div><p class="ccdae-breadcrumb"><a href="{{ route($roadRoute.'index') }}">Vias de trânsito</a> / {{ $road ? 'Editar' : 'Criar' }}</p><h1>{{ $road ? 'Editar via' : 'Nova via' }}</h1></div>@if($road)<form method="post" action="{{ route($roadRoute.'destroy', $road->id) }}" onsubmit="return confirm('Excluir permanentemente esta via?')">@csrf @method('DELETE')<button class="ccdae-theme-toggle" type="submit">Excluir</button></form>@endif</div>
@if($road?->osm_way_id)<p class="ccdae-map-card">ID da via no OpenStreetMap: {{ $road->osm_way_id }}</p>@endif
@if(session('status'))<p role="status" class="ccdae-map-card">{{ session('status') }}</p>@endif
<x-geoveil::form :form="$resource->getForm()" :values="$values" :action="$road ? route($roadRoute.'update', $road->id) : route($roadRoute.'store')" :method="$road ? 'PUT' : 'POST'" />
@endsection
