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
        <div class="ccdae-map-card" aria-label="Legenda dos distritos" style="margin-bottom:16px">
            <strong>Distritos por cor</strong>
            <p>Cada distrito recebe uma cor própria na sequência do mapa. Clique para ver a população e a cidade.</p>
            <a href="{{ route('original-admin.districts') }}">Abrir cadastro de distritos</a>
        </div>
        @endif
        <section class="ccdae-map-card @if(request()->routeIs('original-admin.map-population')) ccdae-population-stage @endif"><x-geoveil::map :map="$map" /></section>
        <p class="ccdae-map-note">@if(request('engine') !== 'leaflet') A superfície distribui os valores dos distritos no espaço. As alturas representam intensidade relativa, não altitude do terreno. @else As cores identificam os distritos; a população aparece ao selecionar cada polígono. @endif</p>
@endsection
