@extends('layouts.ccdae')
@section('title', $record ? 'Editar leitura' : 'Nova leitura')
@section('content')
@php($recordRoute = request()->routeIs('original-traffic.records.*') ? 'original-traffic.records.' : 'traffic.records.')
<div class="ccdae-page-heading"><div><p class="ccdae-breadcrumb"><a href="{{ route($recordRoute.'index') }}">Registros de trânsito</a> / {{ $record ? 'Editar' : 'Criar' }}</p><h1>{{ $record ? 'Editar leitura' : 'Nova leitura' }}</h1></div>@if($record)<form method="post" action="{{ route($recordRoute.'destroy', request()->routeIs('original-traffic.records.*') ? ['record' => \App\Http\Controllers\Map\TrafficRecordController::recordKey($record->via_transito_id, $record->timestamp)] : ['via_id' => $record->via_transito_id, 'at' => $record->timestamp]) }}" onsubmit="return confirm('Excluir permanentemente esta leitura?')">@csrf @method('DELETE')<button class="ccdae-theme-toggle" type="submit">Excluir</button></form>@endif</div>
@if(session('status'))<p role="status" class="ccdae-map-card">{{ session('status') }}</p>@endif
<x-geoveil::form :form="$resource->getForm()" :values="$values" :action="$record ? route($recordRoute.'update', request()->routeIs('original-traffic.records.*') ? ['record' => \App\Http\Controllers\Map\TrafficRecordController::recordKey($record->via_transito_id, $record->timestamp)] : ['via_id' => $record->via_transito_id, 'at' => $record->timestamp]) : route($recordRoute.'store')" :method="$record ? 'PUT' : 'POST'" />
@endsection
