@extends('layouts.ccdae')
@section('title', $resource->title)
@section('content')
@php($canDelete = auth()->user()?->email === config('ccdae.master_email'))
<div class="ccdae-page-heading">
    <div><p class="ccdae-breadcrumb"><a href="{{ route($config['home']) }}">{{ $config['panel'] }}</a> / Ocorrências</p><h1>{{ $resource->title }}</h1></div>
    <a class="ccdae-theme-toggle" href="{{ route('original-occurrence.'.$config['home'].'.create') }}">{{ $config['home'] === 'transito' ? 'Nova infração' : 'Nova ocorrência' }}</a>
</div>
<form method="get" class="ccdae-search ccdae-map-card" role="search">
    <label for="occurrence-search">Buscar</label><input id="occurrence-search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="{{ $config['home'] === 'transito' ? 'Tipo, placa, via ou distrito' : 'Tipo' }}">
    <label for="occurrence-status">Situação</label><select id="occurrence-status" name="situacao"><option value="">Todas</option>@foreach($config['statuses'] as $value => $label)<option value="{{ $value }}" @selected(request('situacao') === $value)>{{ $label }}</option>@endforeach</select>
    <input type="hidden" name="sort" value="{{ $sort }}"><input type="hidden" name="direction" value="{{ $direction }}">
    <button class="ccdae-theme-toggle" type="submit">Filtrar</button>
    @if(request()->filled('q') || request()->filled('situacao'))<a href="{{ url()->current() }}">Limpar</a>@endif
</form>
@if(session('status'))<p role="status" class="ccdae-map-card">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert" class="ccdae-map-card">{{ $errors->first() }}</p>@endif
<section class="ccdae-map-card" style="margin-top:16px">
    <form method="post" action="{{ $config['home'] === 'transito' ? route('original-occurrence.transito.destroy-batch') : route($config['delete']) }}" onsubmit="return confirm('Excluir permanentemente os registros selecionados?')">@csrf
    <div class="ccdae-table-scroll"><table class="ccdae-table"><thead><tr>
        @if($canDelete)<th scope="col">Selecionar</th>@endif
        @foreach($resource->getColumns() as $field => $label)
            <th scope="col" aria-sort="{{ $sort === $field ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                @if(in_array($field, $config['sortable'], true))<a href="{{ request()->fullUrlWithQuery(['sort' => $field, 'direction' => $sort === $field && $direction === 'asc' ? 'desc' : 'asc', 'page' => 1]) }}">{{ $label }} <span aria-hidden="true">{{ $sort === $field ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</span></a>
                @else {{ $label }} @endif
            </th>
        @endforeach
        <th scope="col">Ação</th>
    </tr></thead><tbody>
        @forelse($items as $item)
            <tr>
                @if($canDelete)<td><input type="checkbox" name="selected[]" value="{{ $item->id }}" aria-label="Selecionar registro {{ $item->id }}"></td>@endif
                @foreach($resource->getColumns() as $field => $label)
                    <td>@if($field === 'ocorrido_em' && $item->ocorrido_em){{ \Carbon\Carbon::parse($item->ocorrido_em)->format('d/m/Y H:i') }}
                    @elseif($field === 'valor' && $item->valor !== null)R$ {{ number_format((float) $item->valor, 2, ',', '.') }}
                    @elseif($field === 'usuarios_afetados' && $item->usuarios_afetados !== null){{ number_format($item->usuarios_afetados, 0, ',', '.') }}
                    @elseif(in_array($field, $resource->getBadgedColumns(), true) && $item->$field !== null)<span class="ccdae-table-badge">{{ $item->$field }}</span>
                    @else{{ $item->$field ?? '—' }}@endif</td>
                @endforeach
                <td><a href="{{ route('original-occurrence.'.$config['home'].'.edit', $item->id) }}">Editar</a></td>
            </tr>
        @empty<tr><td colspan="{{ count($resource->getColumns()) + 1 + (int) $canDelete }}">Nenhum registro encontrado.</td></tr>@endforelse
    </tbody></table></div>
    @if($canDelete)<button class="ccdae-theme-toggle" type="submit">Excluir selecionados</button>@endif
    </form>
    <nav class="ccdae-search" aria-label="Paginação">
        @if($items->previousPageUrl())<a href="{{ $items->previousPageUrl() }}">Anterior</a>@endif
        <span>Página {{ $items->currentPage() }}</span>
        @if($items->nextPageUrl())<a href="{{ $items->nextPageUrl() }}">Próxima</a>@endif
    </nav>
</section>
@endsection
