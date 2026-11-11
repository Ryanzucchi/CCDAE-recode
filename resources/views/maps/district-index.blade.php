@extends('layouts.ccdae')
@section('title', 'Distritos')
@section('content')
@php($canEditDistricts = auth()->user()?->email === config('ccdae.master_email'))
@php($districtIndexRoute = request()->routeIs('original-admin.districts') ? 'original-admin.districts' : 'districts.index')
<div class="ccdae-page-heading">
    <div><p class="ccdae-breadcrumb"><a href="{{ route('original-admin.map-population') }}">Território</a> / Distritos</p><h1>Distritos</h1></div>
    <a class="ccdae-theme-toggle" href="{{ route('districts.create') }}" @if($canEditDistricts) data-district-modal-link @endif>Novo distrito</a>
    <a class="ccdae-theme-toggle" href="{{ route('original-admin.map-population') }}">Ver população no mapa</a>
</div>
<section class="ccdae-map-card">
    <form method="get" class="ccdae-search" aria-label="Filtrar mapa por cidade">
        <label for="city-filter">Cidade no mapa</label>
        <select id="city-filter" name="cidade" onchange="this.form.requestSubmit()"><option value="">Selecione uma cidade...</option>@foreach($cities as $option)<option value="{{ $option }}" @selected($city === $option)>{{ $option }}</option>@endforeach</select>
        @if($search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif
        @if(request('sort'))<input type="hidden" name="sort" value="{{ $sort }}">@endif
        @if(request('direction'))<input type="hidden" name="direction" value="{{ $direction }}">@endif
        @if(request('page'))<input type="hidden" name="page" value="{{ $districts->currentPage() }}">@endif
        <button class="ccdae-theme-toggle" type="submit">Ver cidade</button>
    </form>
    <div class="ccdae-district-map-wrap">
        <x-geoveil::map :map="$map" />
        @if($city === '')<div class="ccdae-district-empty" role="status"><div><span aria-hidden="true" class="ccdae-district-empty-icon">⌖</span><h2>Escolha o seu foco</h2><p>Selecione uma cidade para iniciar a visualização geográfica dos distritos.</p></div></div>@endif
    </div>
    <p data-district-map-selection hidden>Distrito selecionado: <strong data-district-map-name></strong> · <a data-district-map-edit @if($canEditDistricts) data-district-modal-link @endif href="#">Editar distrito</a></p>
</section>
<section class="ccdae-map-card" style="margin-top:16px">
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
    <form method="get" class="ccdae-search" role="search">
        <label for="district-search">Buscar por distrito ou cidade</label>
        @if($city !== '')<input type="hidden" name="cidade" value="{{ $city }}">@endif
        @if(request('sort'))<input type="hidden" name="sort" value="{{ $sort }}">@endif
        @if(request('direction'))<input type="hidden" name="direction" value="{{ $direction }}">@endif
        <input id="district-search" name="q" value="{{ $search }}" maxlength="120" type="search">
        <button class="ccdae-theme-toggle" type="submit">Buscar</button>
        @if($search !== '')<a href="{{ route($districtIndexRoute, array_filter(['cidade' => $city, 'sort' => request('sort') ? $sort : null, 'direction' => request('direction') ? $direction : null], fn ($value) => $value !== null && $value !== '')) }}">Limpar busca</a>@endif
    </form>
    <p>{{ $districts->total() }} {{ $districts->total() === 1 ? 'distrito encontrado' : 'distritos encontrados' }}</p>
    <details class="ccdae-column-picker" data-column-picker="districts"><summary>Colunas</summary><div class="ccdae-search"><label><input type="checkbox" data-column-toggle="created_at"> Criado em</label><label><input type="checkbox" data-column-toggle="updated_at"> Atualizado em</label></div></details>
    @if($canEditDistricts)
        <form method="post" action="{{ route('districts.destroy-batch') }}" onsubmit="return confirm('Excluir permanentemente os distritos selecionados?')">
            @csrf
    @endif
    <div class="ccdae-table-scroll"><table class="ccdae-table" data-resource-table="districts">
        <thead><tr>
            @if($canEditDistricts)<th scope="col">Selecionar</th>@endif
            @foreach(['nome' => 'Distrito', 'latitude' => 'Latitude', 'longitude' => 'Longitude', 'cidade' => 'Cidade'] as $field => $label)
                <th scope="col"><a href="{{ route($districtIndexRoute, array_merge(request()->except('page'), ['sort' => $field, 'direction' => $sort === $field && $direction === 'asc' ? 'desc' : 'asc'])) }}">{{ $label }}@if($sort === $field) {{ $direction === 'asc' ? '↑' : '↓' }}@endif</a></th>
            @endforeach
            <th scope="col" data-optional-column="created_at" hidden><a href="{{ request()->fullUrlWithQuery(['sort' => 'created_at', 'direction' => $sort === 'created_at' && $direction === 'asc' ? 'desc' : 'asc', 'page' => 1]) }}">Criado em</a></th>
            <th scope="col" data-optional-column="updated_at" hidden><a href="{{ request()->fullUrlWithQuery(['sort' => 'updated_at', 'direction' => $sort === 'updated_at' && $direction === 'asc' ? 'desc' : 'asc', 'page' => 1]) }}">Atualizado em</a></th>
            <th scope="col">Ações</th>
        </tr></thead>
        <tbody>@forelse($districts as $district)
            <tr>@if($canEditDistricts)<td><input type="checkbox" name="selected[]" value="{{ $district->id }}" aria-label="Selecionar distrito {{ $district->nome }}"></td>@endif<th scope="row">{{ $district->nome }}</th><td>{{ $district->latitude ?? '—' }}</td><td>{{ $district->longitude ?? '—' }}</td><td>{{ $district->cidade }}</td><td data-optional-column="created_at" hidden>{{ $district->created_at?->format('d/m/Y H:i') ?? '—' }}</td><td data-optional-column="updated_at" hidden>{{ $district->updated_at?->format('d/m/Y H:i') ?? '—' }}</td><td><a href="{{ route('districts.edit', $district) }}" @if($canEditDistricts) data-district-modal-link @endif>Editar</a>@if($canEditDistricts) · <button type="submit" form="delete-district-{{ $district->id }}" class="ccdae-link-button" onclick="return confirm('Excluir permanentemente este distrito?')">Excluir</button>@endif</td></tr>
        @empty<tr><td colspan="{{ $canEditDistricts ? 8 : 7 }}">Nenhum distrito encontrado. Tente outro nome ou limpe a busca.</td></tr>@endforelse</tbody>
    </table></div>
    @if($canEditDistricts)
        <button class="ccdae-theme-toggle" type="submit">Excluir selecionados</button>
        </form>
        @foreach($districts as $district)
            <form id="delete-district-{{ $district->id }}" method="post" action="{{ route('districts.destroy', $district) }}">
                @csrf @method('DELETE')
            </form>
        @endforeach
    @endif
    <nav class="ccdae-search" aria-label="Paginação">
        @if($districts->previousPageUrl())<a class="ccdae-theme-toggle" href="{{ $districts->previousPageUrl() }}">Anterior</a>@endif
        <span>Página {{ $districts->currentPage() }} de {{ $districts->lastPage() }}</span>
        @if($districts->nextPageUrl())<a class="ccdae-theme-toggle" href="{{ $districts->nextPageUrl() }}">Próxima</a>@endif
    </nav>
</section>
@if($canEditDistricts)
<dialog class="ccdae-district-dialog" data-district-dialog aria-label="Formulário do distrito">
    <div class="ccdae-district-dialog-header"><strong>Distrito</strong><button type="button" class="ccdae-theme-toggle" data-district-dialog-close aria-label="Fechar formulário">Fechar</button></div>
    <iframe title="Formulário do distrito" data-district-dialog-frame></iframe>
</dialog>
@endif
<script>
document.addEventListener('geoveil:select', event => {
    if (!['distritos', 'distritos-sem-area'].includes(event.detail.layer) || !event.target.closest('[data-geoveil]')) return;
    const feature = event.detail.feature;
    const panel = document.querySelector('[data-district-map-selection]');
    if (!feature || !panel) return;
    panel.querySelector('[data-district-map-name]').textContent = feature.properties.nome || 'Distrito';
    panel.querySelector('[data-district-map-edit]').href = @json(url('/distritos')).concat('/', encodeURIComponent(feature.id), '/editar');
    panel.hidden = false;
});
const districtDialog = document.querySelector('[data-district-dialog]');
if (districtDialog) {
    const frame = districtDialog.querySelector('[data-district-dialog-frame]');
    document.addEventListener('click', event => {
        const link = event.target.closest('[data-district-modal-link], [data-geoveil-modal-link]');
        if (!link) return;
        event.preventDefault();
        const url = new URL(link.href, window.location.href);
        url.searchParams.set('modal', '1');
        frame.src = url.href;
        districtDialog.showModal();
    });
    districtDialog.querySelector('[data-district-dialog-close]').addEventListener('click', () => districtDialog.close());
    districtDialog.addEventListener('close', () => { frame.src = 'about:blank'; });
    window.addEventListener('message', event => {
        if (event.origin !== window.location.origin || event.source !== frame.contentWindow || event.data?.type !== 'ccdae:district-saved') return;
        districtDialog.close();
        window.location.reload();
    });
}
</script>
@endsection
