@extends('layouts.ccdae')
@section('content')
        <div class="ccdae-page-heading">
            <div><p class="ccdae-breadcrumb">Território / População</p><h1>Distribuição populacional</h1></div>
            <nav aria-label="Visualização" class="ccdae-view-switch">
                <a href="{{ url()->current() }}" @if(request('engine') !== 'leaflet') aria-current="page" @endif>Superfície 3D</a>
                <a href="{{ url()->current() }}?engine=leaflet" @if(request('engine') === 'leaflet') aria-current="page" @endif>Distritos 2D</a>
            </nav>
        </div>
        @if(request('engine') === 'leaflet')
        <div class="ccdae-map-card" aria-label="Legenda de população" style="margin-bottom:16px">
            <strong>População por distrito</strong>
            <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:8px">
            @foreach(['#440154' => 'Menos de 500', '#443983' => '500–999', '#31688e' => '1.000–1.499', '#21918c' => '1.500–1.999', '#35b779' => '2.000–2.499', '#90d743' => '2.500–2.999', '#fde725' => '3.000 ou mais'] as $color => $label)
                <span><span aria-hidden="true" style="display:inline-block;width:16px;height:16px;border:1px solid #64748b;background:{{ $color }};vertical-align:middle"></span> {{ $label }}</span>
            @endforeach
            </div>
            <a href="{{ route('original-admin.districts') }}">Abrir cadastro de distritos</a>
        </div>
        @endif
        <section class="ccdae-map-card"><x-geoveil::map :map="$map" /></section>
        <p class="ccdae-map-note">@if(request('engine') !== 'leaflet') A superfície distribui os valores dos distritos no espaço. As alturas representam intensidade relativa, não altitude do terreno. @else População representada por polígonos de distritos. @endif</p>
@endsection
