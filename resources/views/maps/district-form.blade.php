@extends(request()->boolean('modal') ? 'layouts.ccdae-modal' : 'layouts.ccdae')
@section('title', $district->exists ? 'Editar distrito' : 'Novo distrito')
@section('content')
<div class="ccdae-page-heading"><h1>{{ $district->exists ? 'Editar distrito' : 'Novo distrito' }}</h1>@unless(request()->boolean('modal'))<a href="{{ route('districts.index') }}">Voltar aos distritos</a>@endunless</div>
<form class="ccdae-map-card ccdae-form" method="post" action="{{ $district->exists ? route('districts.update', [$district, 'modal' => request()->boolean('modal') ? 1 : null]) : route('districts.store', ['modal' => request()->boolean('modal') ? 1 : null]) }}">
    @csrf @if($district->exists) @method('PUT') @endif
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <label for="nome">Nome do distrito</label><input id="nome" name="nome" required maxlength="255" value="{{ old('nome', $district->nome) }}">
    <label for="cidade">Cidade</label><input id="cidade" name="cidade" required maxlength="255" value="{{ old('cidade', $district->cidade) }}">
    <label for="populacao">População</label><input id="populacao" name="populacao" type="number" min="0" step="1" value="{{ old('populacao', $district->populacao) }}">
    <p>Fonte da população: {{ $district->populacao_fonte ?: 'Não informada' }}</p>
    <label for="consumo">Consumo médio (kWh/dia)</label><input id="consumo" name="consumo_medio_kwh" type="number" min="0" step="0.01" value="{{ old('consumo_medio_kwh', $district->consumo_medio_kwh) }}">
    <p>Se vazio, o mapa de consumo usa a estimativa baseada na população.</p>
    <fieldset data-boundary-editor><legend>Fronteiras e localização</legend>
        <div class="ccdae-form-actions"><button type="button" class="ccdae-theme-toggle" data-boundary-start>Desenhar nova fronteira</button><button type="button" class="ccdae-theme-toggle" data-boundary-rectangle>Desenhar retângulo</button><button type="button" class="ccdae-theme-toggle" data-boundary-finish>Concluir polígono</button><button type="button" class="ccdae-theme-toggle" data-boundary-undo>Desfazer ponto</button><button type="button" class="ccdae-theme-toggle" data-boundary-clear>Apagar fronteira</button><button type="button" class="ccdae-theme-toggle" data-boundary-restore>Restaurar fronteira</button></div>
        <p data-boundary-status role="status">Clique para adicionar pontos. Conclua o polígono e arraste os vértices para ajustar.</p>
        <div data-boundary-map style="height:480px;margin:12px 0" aria-label="Editor de fronteiras"></div>
        <input type="hidden" name="geojson" data-boundary-value value="{{ old('geojson', $district->geojson ? json_encode($district->geojson) : '') }}">
    </fieldset>
    <p>O centro é calculado pela fronteira. No banco do CCDAE, áreas sobrepostas a outros distritos são recortadas ao salvar.</p>
<div class="ccdae-form-actions"><button type="submit" class="ccdae-theme-toggle">Salvar distrito</button>@unless(request()->boolean('modal'))<a href="{{ route('districts.index') }}">Cancelar</a>@endunless</div>
</form>
@if($district->exists)<form method="post" action="{{ route('districts.destroy', [$district, 'modal' => request()->boolean('modal') ? 1 : null]) }}" onsubmit="return confirm('Excluir permanentemente este distrito?')">@csrf @method('DELETE')<button class="ccdae-theme-toggle" type="submit">Excluir distrito</button></form>@endif
@if(request()->boolean('modal') && session('status'))
<script>window.parent.postMessage({ type: 'ccdae:district-saved' }, window.location.origin);</script>
@endif
@endsection
