@extends('layouts.ccdae')
@section('title', 'Painel administrativo')
@section('content')
<div class="ccdae-page-heading"><div><p class="ccdae-breadcrumb">Administração / Visão geral</p><h1>Painel administrativo</h1></div></div>
<div class="ccdae-health-grid">
@foreach($stats as $label => [$value, $description])
<section class="ccdae-map-card"><h2>{{ $label }}</h2><p class="ccdae-dashboard-number" @if($label === 'Registros de Clima') data-climate-count data-url="{{ route('admin.dashboard.climate-count') }}" @endif>{{ $value === null ? ($label === 'Registros de Clima' ? 'Calculando…' : 'Indisponível') : number_format($value, 0, ',', '.') }}</p><p>{{ $description }}</p></section>
@endforeach
</div>
<section class="ccdae-map-card"><h2>Volume de Coleta (Últimos 7 dias)</h2><div class="ccdae-collection-chart" role="img" aria-label="Volume de registros climáticos nos últimos sete dias">
@php($peak = max(1, ...array_column($collection, 'count')))
@foreach($collection as $day)<div class="ccdae-collection-day"><div class="ccdae-collection-bar" style="height:{{ max(2, round($day['count'] / $peak * 100)) }}%" title="{{ number_format($day['count'], 0, ',', '.') }} registros"></div><span>{{ $day['label'] }}</span><strong>{{ number_format($day['count'], 0, ',', '.') }}</strong></div>@endforeach
</div></section>
<p><a class="ccdae-theme-toggle" href="{{ route('system.health') }}">Saúde do Sistema</a></p>
@endsection
