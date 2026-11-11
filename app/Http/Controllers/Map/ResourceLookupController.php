<?php

namespace App\Http\Controllers\Map;

use App\Http\Controllers\Controller;
use App\Services\MapData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResourceLookupController extends Controller
{
    private const RESOURCES = [
        'postes' => ['infrastructure', 'postes', 'codigo_patrimonio'],
        'centrais' => ['infrastructure', 'centrais_distribuicao', 'nome'],
        'antenas' => ['infrastructure', 'antenas', 'codigo_patrimonio'],
        'cabeamentos' => ['infrastructure', 'cabeamentos', 'nome'],
        'equipamentos' => ['infrastructure', 'equipamentos_infraestrutura', 'nome'],
        'vias' => ['mobility', 'via_transitos', 'nome'],
    ];

    public function __invoke(Request $request, string $kind, MapData $data): JsonResponse
    {
        abort_unless(isset(self::RESOURCES[$kind]), 404);
        $input = $request->validate(['q' => 'nullable|string|min:2|max:100', 'id' => 'nullable|integer|min:1', 'domain' => 'nullable|in:internet,energy']);
        if (!isset($input['q']) && !isset($input['id'])) return response()->json(['results' => []]);
        [$schema, $name, $label] = self::RESOURCES[$kind];
        $query = DB::table($data->table($schema, $name));
        if ($kind === 'cabeamentos' && isset($input['domain'])) {
            $query->whereIn('tipo_cabo', $input['domain'] === 'internet'
                ? ['fibra_optica', 'fibra_backbone', 'cobre_telefonia']
                : ['eletrico_baixa_tensao', 'eletrico_alta_tensao']);
        }
        if (isset($input['id'])) $query->where('id', $input['id']);
        else {
            $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($search) use ($input, $label, $operator) {
                $search->where($label, $operator, '%'.$input['q'].'%');
                if (ctype_digit($input['q'])) $search->orWhere('id', (int) $input['q']);
            });
        }
        $coordinateSource = in_array($kind, ['postes', 'centrais'], true);
        $rows = $query->orderBy('id')->limit(20)->get($coordinateSource ? ['id', $label, 'longitude', 'latitude'] : ['id', $label]);
        return response()->json(['results' => $rows->map(fn ($row) => [
            'id' => $row->id,
            'label' => ($row->$label ?: ucfirst($kind)).' #'.$row->id,
            ...($coordinateSource ? ['longitude' => $row->longitude, 'latitude' => $row->latitude] : []),
        ])->all()]);
    }
}
