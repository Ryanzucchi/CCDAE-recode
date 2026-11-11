@extends('layouts.ccdae')
@section('title', $incident ? 'Editar ocorrência de internet' : 'Nova ocorrência de internet')
@section('content')
@php($originalOccurrence = request()->routeIs('original-occurrence.*'))
<div class="ccdae-page-heading"><div><p class="ccdae-breadcrumb"><a href="{{ route('internet') }}">Internet</a> / <a href="{{ route('internet.incidents.index') }}">Incidentes</a></p><h1>{{ $incident ? 'Editar ocorrência' : 'Nova ocorrência' }}</h1></div>@if($incident)<form method="post" action="{{ $originalOccurrence ? route('original-occurrence.internet.destroy', $incident->id) : route('internet.incidents.destroy', $incident->id) }}" onsubmit="return confirm('Excluir permanentemente esta ocorrência?')">@csrf @method('DELETE')<button class="ccdae-theme-toggle" type="submit">Excluir</button></form>@endif</div>
@if(session('status'))<p role="status" class="ccdae-map-card">{{ session('status') }}</p>@endif
<x-geoveil::form :form="$form" :values="$values" :action="$originalOccurrence ? ($incident ? route('original-occurrence.internet.update', $incident->id) : route('original-occurrence.internet.store')) : ($incident ? route('internet.incidents.update', $incident->id) : route('internet.incidents.store'))" :method="$incident ? 'PUT' : 'POST'" />
@endsection
