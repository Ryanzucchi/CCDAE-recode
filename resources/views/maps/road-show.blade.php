@extends('layouts.ccdae')
@section('title', 'Via de trânsito')
@section('content')
@php($roadRoute = request()->routeIs('original-roads.*') ? 'original-roads.' : 'roads.')
<div class="ccdae-page-heading"><div><p class="ccdae-breadcrumb"><a href="{{ route($roadRoute.'index') }}">Vias de trânsito</a> / Visualizar</p><h1>{{ $road->nome ?: 'Via #'.$road->id }}</h1></div><a class="ccdae-theme-toggle" href="{{ route($roadRoute.'edit', $road->id) }}">Editar via</a></div>
<section class="ccdae-map-card"><h2>Dados da via</h2><dl class="ccdae-record-details">
    @foreach(['distrito_id' => 'Distrito', 'osm_way_id' => 'ID OSM', 'tipo' => 'Tipo', 'numero_faixas' => 'Faixas', 'sentido' => 'Sentido', 'direcao_fluxo' => 'Direção do fluxo', 'limite_velocidade' => 'Limite (km/h)', 'estado_pavimento' => 'Pavimento', 'inclinacao_pista' => 'Inclinação (%)', 'nivel_congestionamento' => 'Congestionamento', 'velocidade_media' => 'Velocidade média (km/h)', 'volume_veiculos' => 'Volume de veículos', 'impacto_manutencao' => 'Impacto de manutenção', 'ultima_atualizacao' => 'Última atualização', 'created_at' => 'Criada em', 'updated_at' => 'Alterada em'] as $field => $label)
    <div><dt>{{ $label }}</dt><dd>@if($field === 'distrito_id'){{ $district ?: ($road->distrito_id ?: '—') }}@elseif($field === 'direcao_fluxo'){{ \App\Services\RoadResource::DIRECTIONS[$road->direcao_fluxo] ?? 'Ambos os sentidos' }}@elseif(in_array($field, ['ultima_atualizacao', 'created_at', 'updated_at'], true) && $road->$field){{ \Carbon\Carbon::parse($road->$field)->format('d/m/Y H:i') }}@else{{ $road->$field ?? '—' }}@endif</dd></div>
    @endforeach
</dl></section>
@endsection
