@extends('layouts.ccdae')
@section('title', ($item ? 'Editar ' : 'Criar ').$resource->title)
@section('content')
@php($originalResource = request()->routeIs('original-resource.*'))
<div class="ccdae-page-heading"><div><p class="ccdae-breadcrumb"><a href="{{ $originalResource ? route('original-resource.'.$kind.'.index') : route('infrastructure.resource.index', $kind) }}">{{ $resource->title }}</a> / {{ $item ? 'Editar' : 'Criar' }}</p><h1>{{ $item ? 'Editar' : 'Criar' }} {{ $resource->title }}</h1></div>@if($item)<form method="post" action="{{ $originalResource ? route('original-resource.'.$kind.'.destroy', $item->id) : route('infrastructure.resource.destroy', [$kind, $item->id]) }}" onsubmit="return confirm('Excluir permanentemente este registro?')">@csrf @method('DELETE')<button class="ccdae-theme-toggle" type="submit">Excluir</button></form>@endif</div>
@if(session('status'))<p role="status" class="ccdae-map-card">{{ session('status') }}</p>@endif
<x-geoveil::form :form="$resource->getForm()" :values="$values" :action="$originalResource ? ($item ? route('original-resource.'.$kind.'.update', $item->id) : route('original-resource.'.$kind.'.store')) : ($item ? route('infrastructure.resource.update', [$kind, $item->id]) : route('infrastructure.resource.store', $kind))" :method="$item ? 'PUT' : 'POST'" />
@endsection
