@extends('layouts.ccdae')
@section('title', 'Leitura de trânsito')
@section('content')
@php($recordRoute = request()->routeIs('original-traffic.records.*') ? 'original-traffic.records.' : 'traffic.records.')
<div class="ccdae-page-heading"><div><p class="ccdae-breadcrumb"><a href="{{ route($recordRoute.'index') }}">Registros de trânsito</a> / Visualizar</p><h1>{{ $record->via_nome ?: 'Via #'.$record->via_transito_id }}</h1></div><a class="ccdae-theme-toggle" href="{{ route($recordRoute.'edit', request()->routeIs('original-traffic.records.*') ? ['record' => \App\Http\Controllers\Map\TrafficRecordController::recordKey($record->via_transito_id, $record->timestamp)] : ['via_id' => $record->via_transito_id, 'at' => $record->timestamp]) }}">Editar leitura</a></div>
<section class="ccdae-map-card">
    <h2>Leitura em {{ \Carbon\Carbon::parse($record->timestamp)->format('d/m/Y H:i:s') }}</h2>
    <dl class="ccdae-record-details">
        @foreach(['veiculos_total' => 'Veículos na hora', 'velocidade_media' => 'Velocidade média (km/h)', 'altura_media_veiculos' => 'Altura média (m)', 'altura_maxima_veiculos' => 'Altura máxima (m)', 'indice_congestionamento' => 'Índice de congestionamento', 'nivel_servico' => 'Nível de serviço', 'acidentes_ativos' => 'Acidentes ativos', 'obras_ativas' => 'Obras ativas', 'alagamento_ativo' => 'Alagamento ativo', 'origem_dado' => 'Origem do dado', 'fonte_dado' => 'Fonte do dado', 'confianca_dado' => 'Confiança do dado'] as $field => $label)
        <div><dt>{{ $label }}</dt><dd>@if(!property_exists($record, $field) || $record->$field === null) — @elseif($field === 'alagamento_ativo') {{ $record->$field ? 'Sim' : 'Não' }} @else {{ $record->$field }} @endif</dd></div>
        @endforeach
    </dl>
</section>
@endsection
