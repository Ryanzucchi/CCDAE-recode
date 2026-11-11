<?php

namespace App\Services;

use App\Models\Distrito;
use GeoVeil\Resources\Resource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OccurrenceListing
{
    private const CONFIG = [
        'energia' => [
            'title' => 'Incidentes de energia', 'table' => 'energy.incidentes_energia', 'panel' => 'Energia',
            'columns' => ['ocorrido_em' => 'Data e hora', 'tipo' => 'Tipo', 'causa' => 'Causa', 'distrito_nome' => 'Distrito', 'severidade' => 'Severidade', 'situacao' => 'Situação'],
            'sortable' => ['ocorrido_em', 'severidade', 'situacao'], 'searchable' => ['tipo'],
            'statuses' => ['registrado' => 'Registrado', 'em_atendimento' => 'Em atendimento', 'resolvido' => 'Resolvido', 'cancelado' => 'Cancelado'],
            'create' => 'energy.incidents.create', 'edit' => 'energy.incidents.edit', 'delete' => 'energy.incidents.destroy-batch', 'home' => 'energia',
        ],
        'internet' => [
            'title' => 'Incidentes de internet', 'table' => 'internet.incidentes_internet', 'panel' => 'Internet',
            'columns' => ['ocorrido_em' => 'Data e hora', 'tipo' => 'Tipo', 'causa' => 'Causa', 'distrito_nome' => 'Distrito', 'usuarios_afetados' => 'Usuários afetados', 'severidade' => 'Severidade', 'situacao' => 'Situação'],
            'sortable' => ['ocorrido_em'], 'searchable' => ['tipo'],
            'statuses' => ['registrado' => 'Registrado', 'em_atendimento' => 'Em atendimento', 'resolvido' => 'Resolvido', 'cancelado' => 'Cancelado'],
            'create' => 'internet.incidents.create', 'edit' => 'internet.incidents.edit', 'delete' => 'internet.incidents.destroy-batch', 'home' => 'internet',
        ],
        'transito' => [
            'title' => 'Infrações de trânsito', 'table' => 'mobility.infracoes_transito', 'panel' => 'Trânsito',
            'columns' => ['ocorrido_em' => 'Data e hora', 'tipo' => 'Tipo', 'placa' => 'Placa', 'via_nome' => 'Via', 'distrito_nome' => 'Distrito', 'valor' => 'Valor', 'pontos' => 'Pontos', 'situacao' => 'Situação'],
            'sortable' => ['ocorrido_em', 'tipo', 'valor', 'pontos', 'situacao'], 'searchable' => ['tipo', 'placa', 'via_nome', 'distrito_nome'],
            'statuses' => ['registrada' => 'Registrada', 'notificada' => 'Notificada', 'cancelada' => 'Cancelada', 'paga' => 'Paga'],
            'create' => 'traffic.infractions.create', 'edit' => 'traffic.infractions.edit', 'delete' => 'traffic.infractions.destroy-batch', 'home' => 'transito',
        ],
    ];

    public function viewData(string $module, Request $request, MapData $data): array
    {
        $config = self::CONFIG[$module];
        $input = $request->validate([
            'q' => 'nullable|string|max:100',
            'situacao' => ['nullable', Rule::in(array_keys($config['statuses']))],
            'sort' => ['nullable', Rule::in($config['sortable'])],
            'direction' => 'nullable|in:asc,desc',
        ]);
        $sort = $input['sort'] ?? 'ocorrido_em';
        $direction = $input['direction'] ?? 'desc';
        $badges = $module === 'transito' ? ['situacao'] : ['tipo', 'causa', 'severidade', 'situacao'];
        $resource = Resource::make($module.'-ocorrencias', $config['title'], $config['table'])
            ->columns($config['columns'])->badgedColumns($badges);
        [$schema, $name] = explode('.', $config['table']);
        $districtTable = (new Distrito)->getTable();
        $query = DB::table($data->table($schema, $name).' as o')
            ->leftJoin($districtTable.' as d', 'd.id', '=', 'o.distrito_id');
        if ($module === 'transito') $query->leftJoin($data->table('mobility', 'via_transitos').' as v', 'v.id', '=', 'o.via_transito_id');
        $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $searchColumns = ['tipo' => 'o.tipo', 'placa' => 'o.placa', 'distrito_nome' => 'd.nome', 'via_nome' => 'v.nome'];
        $query->when($input['q'] ?? null, function ($query, $term) use ($config, $searchColumns, $operator) {
            $query->where(function ($query) use ($config, $searchColumns, $operator, $term) {
                foreach ($config['searchable'] as $field) $query->orWhere($searchColumns[$field], $operator, '%'.$term.'%');
            });
        })->when($input['situacao'] ?? null, fn ($query, $status) => $query->where('o.situacao', $status));
        $items = $query->select('o.*', 'd.nome as distrito_nome')
            ->when($module === 'transito', fn ($query) => $query->addSelect('v.nome as via_nome'))
            ->orderBy('o.'.$sort, $direction)->orderByDesc('o.ocorrido_em')->orderByDesc('o.id')
            ->simplePaginate(20)->withQueryString();
        return compact('resource', 'items', 'sort', 'direction', 'config');
    }
}
