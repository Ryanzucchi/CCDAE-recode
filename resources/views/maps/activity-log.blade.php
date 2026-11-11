@extends('layouts.ccdae')
@section('title', 'Atividade #'.$item->id)
@section('content')
@php($properties = json_decode($item->properties ?? '{}', true) ?: [])
<div class="ccdae-page-heading"><div><p class="ccdae-breadcrumb"><a href="{{ route('activity.logs.index') }}">Atividades</a> / Registro #{{ $item->id }}</p><h1>Atividade #{{ $item->id }}</h1></div></div>
<div class="ccdae-health-grid">
    <section class="ccdae-map-card"><h2>Detalhes da Ação</h2><dl class="ccdae-record-details">
        <div><dt>Ação</dt><dd>{{ $item->description }}</dd></div>
        <div><dt>Modelo</dt><dd>{{ $item->subject_type ? class_basename($item->subject_type) : 'Sistema/Global' }}</dd></div>
        <div><dt>Usuário</dt><dd>{{ $item->causer_name ?? 'Sistema' }}</dd></div>
    </dl></section>
    <section class="ccdae-map-card"><h2>Timestamp</h2><dl class="ccdae-record-details"><div><dt>Data/Hora</dt><dd>{{ \Carbon\Carbon::parse($item->created_at)->format('d/m/Y H:i:s') }}</dd></div></dl></section>
</div>
<section class="ccdae-map-card"><h2>Mudanças de Dados</h2><div class="ccdae-health-grid">
    <div><h3>Antes</h3><pre>{{ json_encode($properties['old'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></div>
    <div><h3>Depois</h3><pre>{{ json_encode($properties['attributes'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></div>
</div></section>
@endsection
