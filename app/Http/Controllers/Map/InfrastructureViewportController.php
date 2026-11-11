<?php

namespace App\Http\Controllers\Map;

use App\Http\Controllers\Controller;
use App\Models\Distrito;
use App\Services\MapData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InfrastructureViewportController extends Controller
{
    private const LAYERS = [
        'global' => [
            ['postes', 'codigo_patrimonio', true, null],
            ['antenas', 'codigo_patrimonio', true, null],
            ['centrais_distribuicao', 'nome', true, null, 'point'],
            ['equipamentos_infraestrutura', 'nome', true, null],
            ['cabeamentos', 'nome', false, null],
            ['centrais_distribuicao', 'nome', false, null, 'polygon'],
            ['via_transitos', 'nome', false, null, 'line', 'mobility'],
        ],
        'energia' => [
            ['postes', 'codigo_patrimonio', true, null],
            ['equipamentos_infraestrutura', 'nome', true, null],
            ['centrais_distribuicao', 'nome', true, null],
            ['cabeamentos', 'nome', false, 'eletrico_baixa_tensao'],
            ['cabeamentos', 'nome', false, 'eletrico_alta_tensao'],
        ],
        'internet' => [
            ['antenas', 'codigo_patrimonio', true, null],
            ['equipamentos_infraestrutura', 'nome', true, null],
            ['cabeamentos', 'nome', false, 'fibra_optica'],
            ['cabeamentos', 'nome', false, 'fibra_backbone'],
            ['cabeamentos', 'nome', false, 'cobre_telefonia'],
        ],
    ];

    public function __invoke(Request $request, MapData $data, string $module, int $layer)
    {
        abort_unless(isset(self::LAYERS[$module][$layer]), 404);
        $input = $request->validate([
            'west' => 'required|numeric|between:-180,180',
            'south' => 'required|numeric|between:-90,90',
            'east' => 'required|numeric|between:-180,180',
            'north' => 'required|numeric|between:-90,90',
            'distrito_id' => 'nullable|integer',
        ]);
        if (isset($input['distrito_id'])) abort_unless(Distrito::query()->whereKey($input['distrito_id'])->exists(), 422);
        [$west, $south, $east, $north] = array_map(fn ($key) => (float) $input[$key], ['west', 'south', 'east', 'north']);
        abort_if($east <= $west || $north <= $south || $east - $west > 2 || $north - $south > 2, 422);
        [$name, $field, $points, $cableType, $geometryKind, $schema] = array_pad(self::LAYERS[$module][$layer], 6, null);
        $table = $data->table($schema ?: 'infrastructure', $name);
        abort_unless($data->exists($table), 404);
        $query = DB::table($table)->when($input['distrito_id'] ?? null, fn ($q, $id) => $q->where('distrito_id', $id))
            ->when($cableType, fn ($q) => $q->where('tipo_cabo', $cableType));
        if (DB::connection()->getDriverName() === 'pgsql') {
            $query->whereRaw('geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)', [$west, $south, $east, $north]);
            if ($geometryKind === 'point') $query->whereRaw("ST_GeometryType(geom) = 'ST_Point'");
            if ($geometryKind === 'polygon') $query->whereRaw("ST_GeometryType(geom) IN ('ST_Polygon', 'ST_MultiPolygon')");
        } elseif ($points) {
            $query->whereBetween('longitude', [$west, $east])->whereBetween('latitude', [$south, $north]);
        }
        $properties = [$field];
        if ($module === 'global' && \Illuminate\Support\Facades\Schema::hasColumn($table, 'estado_conservacao')) $properties[] = 'estado_conservacao';
        if ($module === 'global' && $schema === 'mobility') {
            $properties[] = 'nivel_congestionamento';
            $properties[] = 'velocidade_media';
        }
        $columns = array_merge(['id'], $properties, $points ? ['longitude', 'latitude'] : ['geojson']);
        if ($geometryKind === 'point' && DB::connection()->getDriverName() !== 'pgsql') $columns[] = 'geojson';
        $rows = $query->get(array_unique($columns));
        if ($geometryKind === 'point' && DB::connection()->getDriverName() !== 'pgsql') {
            $rows = $rows->filter(function ($row) {
                $geometry = is_string($row->geojson) ? json_decode($row->geojson, true) : $row->geojson;
                return ! in_array($geometry['type'] ?? null, ['Polygon', 'MultiPolygon'], true);
            });
        }
        $features = $data->features($rows, $properties, $points)->jsonSerialize()['features'];
        if ($geometryKind === 'polygon') $features = array_values(array_filter($features,
            fn ($feature) => in_array($feature['geometry']['type'], ['Polygon', 'MultiPolygon'], true)));
        if (DB::connection()->getDriverName() !== 'pgsql' && ! $points) {
            $inside = function ($coordinates) use (&$inside, $west, $south, $east, $north): bool {
                if (is_numeric($coordinates[0] ?? null)) {
                    return $coordinates[0] >= $west && $coordinates[0] <= $east
                        && $coordinates[1] >= $south && $coordinates[1] <= $north;
                }
                foreach ($coordinates as $child) if ($inside($child)) return true;
                return false;
            };
            $features = array_values(array_filter($features, fn ($feature) => $inside($feature['geometry']['coordinates'])));
        }
        return response()->json(['type' => 'FeatureCollection', 'features' => $features]);
    }
}
