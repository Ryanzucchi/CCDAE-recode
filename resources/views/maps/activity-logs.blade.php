@extends('layouts.ccdae')
@section('title', 'Histórico de atividades')
@section('content')
<div class="ccdae-page-heading"><div><p class="ccdae-breadcrumb">Administração / Atividades</p><h1>Histórico de atividades</h1></div></div>
@if(session('status'))<p role="status" class="ccdae-map-card">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert" class="ccdae-map-card">{{ $errors->first() }}</p>@endif
<form method="get" class="ccdae-search ccdae-map-card">
    <label for="q">Evento, canal ou modelo</label><input id="q" name="q" value="{{ request('q') }}" placeholder="Buscar atividade">
    <label for="canal">Canal</label><input id="canal" name="canal" value="{{ request('canal') }}" placeholder="Todos os canais">
    <button class="ccdae-theme-toggle" type="submit">Buscar</button>
</form>
<section class="ccdae-map-card"><details class="ccdae-column-picker" data-column-picker="activity-logs"><summary>Colunas</summary><div class="ccdae-search"><label><input type="checkbox" data-column-toggle="subject_id"> ID do Registro</label></div></details><form method="post" action="{{ route('activity.logs.destroy-batch') }}" onsubmit="return confirm('Excluir permanentemente as atividades selecionadas?')">@csrf<div class="ccdae-table-scroll"><table class="ccdae-table" data-resource-table="activity-logs"><thead><tr>
    <th scope="col">Selecionar</th>
    <th scope="col" aria-sort="{{ $direction === 'asc' ? 'ascending' : 'descending' }}"><a href="{{ request()->fullUrlWithQuery(['sort' => 'created_at', 'direction' => $direction === 'asc' ? 'desc' : 'asc', 'page' => 1]) }}">Horário {{ $direction === 'asc' ? '↑' : '↓' }}</a></th>
    <th scope="col">Canal/Log</th><th scope="col">Evento</th><th scope="col">Tabela/Model</th><th scope="col" data-optional-column="subject_id" hidden>ID do Registro</th><th scope="col">Usuário</th><th scope="col">Ação</th>
</tr></thead><tbody data-activity-rows>
@forelse($items as $item)
    @php($eventColor = match($item->description) { 'created' => 'success', 'updated' => 'info', 'deleted' => 'danger', default => 'warning' })
    <tr>
        <td><input type="checkbox" name="selected[]" value="{{ $item->id }}" aria-label="Selecionar atividade #{{ $item->id }}"></td>
        <td><strong>{{ \Carbon\Carbon::parse($item->created_at)->format('H:i:s') }}</strong><small class="ccdae-table-secondary">{{ \Carbon\Carbon::parse($item->created_at)->format('d/m/Y') }}</small></td>
        <td><span class="ccdae-log-badge">{{ $item->log_name === 'default' ? 'Geral' : $item->log_name }}</span></td>
        <td><span class="ccdae-log-badge ccdae-log-{{ $eventColor }}">{{ $item->description }}</span></td>
        <td>{{ $item->subject_type ? class_basename($item->subject_type) : 'Sistema/Global' }}</td>
        <td data-optional-column="subject_id" hidden>{{ $item->subject_id ?? '—' }}</td>
        <td>{{ $item->causer_name ?? 'Sistema' }}<small class="ccdae-table-secondary">{{ $item->causer_type ? class_basename($item->causer_type) : 'Tarefa de Background' }}</small></td>
        <td><a href="{{ route('activity.logs.show', $item->id) }}">Visualizar</a></td>
    </tr>
@empty<tr><td colspan="8">Nenhuma atividade encontrada.</td></tr>@endforelse
</tbody></table></div><button class="ccdae-theme-toggle" type="submit">Excluir selecionadas</button></form><nav class="ccdae-search" aria-label="Paginação" data-activity-pagination>@if($items->previousPageUrl())<a href="{{ $items->previousPageUrl() }}">Anterior</a>@endif <span>Página {{ $items->currentPage() }}</span> @if($items->nextPageUrl())<a href="{{ $items->nextPageUrl() }}">Próxima</a>@endif</nav></section>
<script>
(() => {
    const rows = document.querySelector('[data-activity-rows]');
    const pagination = document.querySelector('[data-activity-pagination]');
    if (!rows || !pagination) return;
    let pending = false;
    const abort = new AbortController();
    const timer = window.setInterval(async () => {
        if (pending || document.hidden || rows.querySelector('input[name="selected[]"]:checked')) return;
        pending = true;
        try {
            const response = await fetch(window.location.href, { headers: { Accept: 'text/html' }, cache: 'no-store', signal: abort.signal });
            if (!response.ok || response.redirected) return;
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const nextRows = page.querySelector('[data-activity-rows]');
            const nextPagination = page.querySelector('[data-activity-pagination]');
            if (nextRows && nextPagination && !rows.querySelector('input[name="selected[]"]:checked')) {
                rows.replaceChildren(...nextRows.children);
                pagination.replaceChildren(...nextPagination.childNodes);
                rows.closest('[data-resource-table]')?.dispatchEvent(new Event('geoveil:table-updated'));
            }
        } catch (_) {
            // A próxima atualização tenta novamente sem interromper a consulta atual.
        } finally { pending = false; }
    }, 2000);
    window.addEventListener('pagehide', () => { window.clearInterval(timer); abort.abort(); }, { once: true });
})();
</script>
@endsection
