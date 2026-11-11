@extends('layouts.ccdae')
@section('title', $infraction ? 'Editar infração de trânsito' : 'Nova infração de trânsito')
@section('content')
@php($originalOccurrence = request()->routeIs('original-occurrence.*'))
<div class="ccdae-page-heading"><div><p class="ccdae-breadcrumb"><a href="{{ route('transito') }}">Trânsito</a> / <a href="{{ route('original-occurrence.transito.index') }}">Infrações</a></p><h1>{{ $infraction ? 'Editar infração' : 'Nova infração' }}</h1></div>@if($infraction)<form method="post" action="{{ $originalOccurrence ? route('original-occurrence.transito.destroy', $infraction->id) : route('traffic.infractions.destroy', $infraction->id) }}" onsubmit="return confirm('Excluir permanentemente esta infração?')">@csrf @method('DELETE')<button class="ccdae-theme-toggle" type="submit">Excluir</button></form>@endif</div>
@if(session('status'))<p role="status" class="ccdae-map-card">{{ session('status') }}</p>@endif
<x-geoveil::form :form="$form" :values="$values" :action="$originalOccurrence ? ($infraction ? route('original-occurrence.transito.update', $infraction->id) : route('original-occurrence.transito.store')) : ($infraction ? route('traffic.infractions.update', $infraction->id) : route('traffic.infractions.store'))" :method="$infraction ? 'PUT' : 'POST'" />
@endsection
