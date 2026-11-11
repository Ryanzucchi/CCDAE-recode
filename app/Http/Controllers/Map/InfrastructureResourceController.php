<?php

namespace App\Http\Controllers\Map;

use App\Http\Controllers\Controller;
use App\Models\Distrito;
use App\Services\InfrastructureResourceCatalog;
use App\Services\MapData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InfrastructureResourceController extends Controller
{
    public function index(Request $request, string $kind, InfrastructureResourceCatalog $catalog, MapData $data)
    {
        $resource = $catalog->get($kind);
        $input = $request->validate([
            'q' => 'nullable|string|max:100',
            'distrito_id' => ['nullable', 'integer', Rule::exists((new Distrito)->getTable(), 'id')],
            'sort' => ['nullable', Rule::in($catalog->sortable($kind))],
            'direction' => 'nullable|in:asc,desc',
        ]);
        $table = $data->table('infrastructure', $kind);
        $sort = $input['sort'] ?? 'id';
        $direction = $input['direction'] ?? 'desc';
        $searchable = $catalog->searchable($kind);
        $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $items = DB::table($table)
            ->when($input['q'] ?? null, function ($query, $q) use ($searchable, $operator) {
                $query->where(function ($search) use ($searchable, $operator, $q) {
                    foreach ($searchable as $field) $search->orWhere($field, $operator, '%'.$q.'%');
                });
            })
            ->when($input['distrito_id'] ?? null, fn ($query, $id) => $query->where('distrito_id', $id))
            ->orderBy($sort, $direction)->when($sort !== 'id', fn ($query) => $query->orderBy('id'))
            ->paginate(20)->withQueryString();
        $districts = Distrito::query()->orderBy('nome')->pluck('nome', 'id')->all();
        return view('maps.infrastructure-resource-index', compact('resource', 'kind', 'items', 'districts', 'sort', 'direction', 'catalog'));
    }

    public function create(string $kind, InfrastructureResourceCatalog $catalog)
    {
        $resource = $catalog->get($kind); $item = null; $values = [];
        return view('maps.infrastructure-resource-form', compact('resource', 'kind', 'item', 'values'));
    }

    public function edit(string $kind, int $id, InfrastructureResourceCatalog $catalog, MapData $data)
    {
        abort_unless(in_array($kind, ['postes', 'antenas', 'equipamentos_infraestrutura', 'centrais_distribuicao', 'cabeamentos'], true), 404);
        $item = DB::table($data->table('infrastructure', $kind))->where('id', $id)->first();
        abort_unless($item, 404);
        $resource = $catalog->get($kind, $item);
        $values = (array) $item;
        return view('maps.infrastructure-resource-form', compact('resource', 'kind', 'item', 'values'));
    }

    public function store(Request $request, string $kind, InfrastructureResourceCatalog $catalog, MapData $data)
    {
        $catalog->get($kind);
        return $this->save($request, $kind, $data);
    }

    public function update(Request $request, string $kind, int $id, InfrastructureResourceCatalog $catalog, MapData $data)
    {
        $catalog->get($kind);
        abort_unless(DB::table($data->table('infrastructure', $kind))->where('id', $id)->exists(), 404);
        return $this->save($request, $kind, $data, $id);
    }

    public function destroyBatch(Request $request, string $kind, InfrastructureResourceCatalog $catalog, MapData $data)
    {
        $catalog->tableColumns($kind);
        $table = $data->table('infrastructure', $kind);
        $input = $request->validate([
            'selected' => 'required|array|min:1|max:20',
            'selected.*' => ['required', 'integer', 'distinct', Rule::exists($table, 'id')],
        ]);
        $deleted = DB::transaction(fn () => DB::table($table)->whereIn('id', $input['selected'])->delete());
        $indexRoute = $request->routeIs('original-resource.*') ? 'original-resource.'.$kind.'.index' : 'infrastructure.resource.index';
        return redirect()->route($indexRoute, $indexRoute === 'infrastructure.resource.index' ? $kind : [])->with('status', "$deleted registros excluídos.");
    }

    public function destroy(Request $request, string $kind, int $id, InfrastructureResourceCatalog $catalog, MapData $data)
    {
        $catalog->tableColumns($kind);
        $deleted = DB::table($data->table('infrastructure', $kind))->where('id', $id)->delete();
        abort_unless($deleted, 404);
        $indexRoute = $request->routeIs('original-resource.*') ? 'original-resource.'.$kind.'.index' : 'infrastructure.resource.index';
        return redirect()->route($indexRoute, $indexRoute === 'infrastructure.resource.index' ? $kind : [])->with('status', 'Registro excluído.');
    }

    private function save(Request $request, string $kind, MapData $data, ?int $id = null)
    {
        $table = $data->table('infrastructure', $kind);
        $rules = [
            'distrito_id' => ['nullable', 'integer', Rule::exists((new Distrito)->getTable(), 'id')],
            'codigo_patrimonio' => ['nullable', 'string', 'max:255', Rule::unique($table, 'codigo_patrimonio')->ignore($id)],
            'estado_conservacao' => ['required', Rule::in(array_keys(InfrastructureResourceCatalog::CONSERVATION))],
            'data_instalacao' => 'nullable|date', 'ultima_manutencao' => 'nullable|date',
            'latitude' => 'nullable|numeric|between:-90,90|required_with:longitude',
            'longitude' => 'nullable|numeric|between:-180,180|required_with:latitude',
            'observacoes' => 'nullable|string|max:20000',
        ];
        if ($kind === 'postes') $rules += [
            'material' => ['required', Rule::in(array_keys(InfrastructureResourceCatalog::MATERIALS))],
            'altura_metros' => 'nullable|numeric|between:0,999.99',
            'resistencia_kg' => 'nullable|integer|min:0',
            'possui_iluminacao' => 'required|boolean',
        ];
        elseif ($kind === 'antenas') $rules += [
            'tipo_sinal' => ['nullable', Rule::in(array_keys(InfrastructureResourceCatalog::SIGNALS))],
            'frequencia_mhz' => 'nullable|numeric|between:0,999999.99',
            'alcance_metros' => 'nullable|numeric|between:0,999999.99',
            'potencia_dbm' => 'nullable|numeric|between:-999999.99,999999.99',
            'proprietario' => 'nullable|string|max:255',
        ];
        elseif ($kind === 'equipamentos_infraestrutura') $rules += [
            'nome' => 'required|string|max:255', 'tipo' => 'nullable|string|max:255',
            'modelo' => 'nullable|string|max:255', 'numero_serie' => 'nullable|string|max:255',
            'poste_id' => ['nullable', 'integer', Rule::exists($data->table('infrastructure', 'postes'), 'id')],
            'central_id' => ['nullable', 'integer', Rule::exists($data->table('infrastructure', 'centrais_distribuicao'), 'id')],
        ];
        elseif ($kind === 'centrais_distribuicao') $rules += [
            'nome' => 'required|string|max:255',
            'tipo' => ['nullable', Rule::in(array_merge(['Energia', 'Telecom', 'Agua', 'Gas'], $id ? array_filter([DB::table($table)->where('id', $id)->value('tipo')]) : []))],
            'capacidade' => 'nullable|string|max:255', 'area_m2' => 'nullable|numeric|between:0,999999.99',
            'geojson' => 'nullable|string|max:100000',
        ];
        else $rules = [
            'distrito_id' => ['nullable', 'integer', Rule::exists((new Distrito)->getTable(), 'id')],
            'codigo_patrimonio' => ['nullable', 'string', 'max:255', Rule::unique($table, 'codigo_patrimonio')->ignore($id)],
            'nome' => 'nullable|string|max:255',
            'tipo_cabo' => ['nullable', Rule::in(array_keys(InfrastructureResourceCatalog::CABLE_TYPES))],
            'capacidade' => 'nullable|string|max:255', 'revestimento' => 'nullable|string|max:255',
            'extensao_metros' => 'nullable|numeric|between:0,99999999.99', 'subterraneo' => 'required|boolean',
            'estado_conservacao' => ['required', Rule::in(array_keys(InfrastructureResourceCatalog::CONSERVATION))],
            'data_instalacao' => 'nullable|date', 'ultima_manutencao' => 'nullable|date',
            'observacoes' => 'nullable|string|max:20000', 'geojson' => 'nullable|string|max:200000',
            'geometry_manual' => 'nullable|boolean',
            'poste_origem_id' => ['nullable', 'integer', Rule::exists($data->table('infrastructure', 'postes'), 'id')],
            'poste_destino_id' => ['nullable', 'integer', Rule::exists($data->table('infrastructure', 'postes'), 'id')],
            'central_origem_id' => ['nullable', 'integer', Rule::exists($data->table('infrastructure', 'centrais_distribuicao'), 'id')],
            'central_destino_id' => ['nullable', 'integer', Rule::exists($data->table('infrastructure', 'centrais_distribuicao'), 'id')],
        ];
        $input = $request->validate($rules);
        if ($kind === 'cabeamentos') {
            $manual = (bool) ($input['geometry_manual'] ?? false);
            unset($input['geometry_manual']);
            $routeInput = $input;
            if ($id && !$manual) {
                $existing = DB::table($table)->where('id', $id)->first(['poste_origem_id', 'poste_destino_id', 'central_origem_id', 'central_destino_id']);
                $changed = false;
                foreach (['poste_origem_id', 'poste_destino_id', 'central_origem_id', 'central_destino_id'] as $node) {
                    if (array_key_exists($node, $input) && $input[$node] != ($existing->$node ?? null)) { $changed = true; break; }
                }
                if ($changed) {
                    $routeInput = array_replace((array) $existing, $input);
                    $routeInput['geojson'] = null;
                }
            }
            $this->prepareCableGeometry($routeInput, $data);
            if (($changed ?? false) && empty($routeInput['geojson'])) $routeInput['geojson'] = $input['geojson'] ?? null;
            if (array_key_exists('geojson', $routeInput)) $input['geojson'] = $routeInput['geojson'];
            if (array_key_exists('extensao_metros', $routeInput)) $input['extensao_metros'] = $routeInput['extensao_metros'];
        }
        if ($kind === 'centrais_distribuicao' && !empty($input['geojson'])) {
            $geometry = json_decode($input['geojson'], true);
            if (!is_array($geometry) || !in_array($geometry['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['geojson' => 'Desenhe um polígono válido.']);
            }
            try { \GeoVeil\Sources\GeoJson::validateGeometry($geometry); }
            catch (\InvalidArgumentException $e) { throw \Illuminate\Validation\ValidationException::withMessages(['geojson' => $e->getMessage()]); }
            if (DB::connection()->getDriverName() === 'pgsql') {
                $center = DB::selectOne('SELECT ST_X(ST_Centroid(ST_GeomFromGeoJSON(?))) AS longitude, ST_Y(ST_Centroid(ST_GeomFromGeoJSON(?))) AS latitude', [$input['geojson'], $input['geojson']]);
                $input['latitude'] = round((float) $center->latitude, 7);
                $input['longitude'] = round((float) $center->longitude, 7);
            } else {
                $ring = $geometry['type'] === 'Polygon' ? $geometry['coordinates'][0] : $geometry['coordinates'][0][0];
                $points = array_slice($ring, 0, -1);
                $input['longitude'] = array_sum(array_column($points, 0)) / count($points);
                $input['latitude'] = array_sum(array_column($points, 1)) / count($points);
            }
        }
        $input['updated_at'] = now();
        if ($id) DB::table($table)->where('id', $id)->update($input);
        else { $input['created_at'] = now(); $id = DB::table($table)->insertGetId($input); }
        $editRoute = $request->routeIs('original-resource.*') ? 'original-resource.'.$kind.'.edit' : 'infrastructure.resource.edit';
        return redirect()->route($editRoute, $editRoute === 'infrastructure.resource.edit' ? [$kind, $id] : $id)->with('status', 'Registro salvo.');
    }
    private function prepareCableGeometry(array &$input, MapData $data): void
    {
        if (empty($input['geojson'])) {
            $from = $this->nodeCoordinates($input['poste_origem_id'] ?? null, $input['central_origem_id'] ?? null, $data);
            $to = $this->nodeCoordinates($input['poste_destino_id'] ?? null, $input['central_destino_id'] ?? null, $data);
            if ($from && $to) $input['geojson'] = json_encode(['type' => 'LineString', 'coordinates' => [$from, $to]], JSON_THROW_ON_ERROR);
        }
        if (empty($input['geojson'])) return;
        $geometry = json_decode($input['geojson'], true);
        if (!is_array($geometry) || !in_array($geometry['type'] ?? null, ['LineString', 'MultiLineString'], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['geojson' => 'Desenhe uma linha válida.']);
        }
        try { \GeoVeil\Sources\GeoJson::validateGeometry($geometry); }
        catch (\InvalidArgumentException $e) { throw \Illuminate\Validation\ValidationException::withMessages(['geojson' => $e->getMessage()]); }
        if (DB::connection()->getDriverName() === 'pgsql') {
            $length = DB::selectOne('SELECT ST_Length(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)::geography) AS metros', [$input['geojson']]);
            $input['extensao_metros'] = round((float) $length->metros, 2);
        } else {
            $lines = $geometry['type'] === 'LineString' ? [$geometry['coordinates']] : $geometry['coordinates'];
            $meters = 0.0;
            foreach ($lines as $line) for ($i = 1; $i < count($line); $i++) {
                [$x1, $y1] = $line[$i - 1]; [$x2, $y2] = $line[$i];
                $meters += hypot(($x2 - $x1) * cos(deg2rad(($y1 + $y2) / 2)) * 111320, ($y2 - $y1) * 110540);
            }
            $input['extensao_metros'] = round($meters, 2);
        }
    }

    private function nodeCoordinates(?int $postId, ?int $centralId, MapData $data): ?array
    {
        foreach ([['postes', $postId], ['centrais_distribuicao', $centralId]] as [$table, $id]) {
            if (!$id) continue;
            $row = DB::table($data->table('infrastructure', $table))->where('id', $id)->first(['longitude', 'latitude']);
            if ($row && is_numeric($row->longitude) && is_numeric($row->latitude)) return [(float) $row->longitude, (float) $row->latitude];
        }
        return null;
    }

}
