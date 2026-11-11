@extends('layouts.ccdae')
@section('title', $incident ? 'Editar ocorrência de energia' : 'Nova ocorrência de energia')
@section('content')
@php($originalOccurrence = request()->routeIs('original-occurrence.*'))
<div class="ccdae-page-heading"><div><p class="ccdae-breadcrumb"><a href="{{ route('energia') }}">Energia</a> / <a href="{{ route('energy.incidents.index') }}">Incidentes</a></p><h1>{{ $incident ? 'Editar ocorrência' : 'Nova ocorrência' }}</h1></div>@if($incident)<form method="post" action="{{ $originalOccurrence ? route('original-occurrence.energia.destroy', $incident->id) : route('energy.incidents.destroy', $incident->id) }}" onsubmit="return confirm('Excluir permanentemente esta ocorrência?')">@csrf @method('DELETE')<button class="ccdae-theme-toggle" type="submit">Excluir</button></form>@endif</div>
@if(session('status'))<p role="status" class="ccdae-map-card">{{ session('status') }}</p>@endif
<x-geoveil::form :form="$form" :values="$values" :action="$originalOccurrence ? ($incident ? route('original-occurrence.energia.update', $incident->id) : route('original-occurrence.energia.store')) : ($incident ? route('energy.incidents.update', $incident->id) : route('energy.incidents.store'))" :method="$incident ? 'PUT' : 'POST'" />
@endsection
