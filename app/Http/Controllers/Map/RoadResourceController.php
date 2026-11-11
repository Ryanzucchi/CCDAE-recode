<?php

namespace App\Http\Controllers\Map;

use App\Http\Controllers\Controller;
use App\Models\Distrito;
use App\Services\{MapData, RoadResource};
use GeoVeil\Sources\GeoJson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoadResourceController extends Controller
{
    public function index(Request $request, MapData $data, RoadResource $catalog)
    {
        $input = $request->validate([
            'q' => 'nullable|string|max:100',
            'distrito_id' => ['nullable', 'integer', Rule::exists((new Distrito)->getTable(), 'id')],
            'sort' => ['nullable', Rule::in(['tipo', 'numero_faixas', 'limite_velocidade', 'velocidade_media', 'volume_veiculos', 'ultima_atualizacao'])],
            'direction' => 'nullable|in:asc,desc',
        ]);
        $resource = $catalog->get();
        $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $sort = $input['sort'] ?? null;
        $direction = $input['direction'] ?? 'asc';
        $items = DB::table($data->table('mobility', 'via_transitos'))
            ->when($input['q'] ?? null, fn ($query, $q) => $query->where(fn ($query) => $query
                ->where('nome', $operator, '%'.$q.'%')->orWhere('tipo', $operator, '%'.$q.'%')
                ->orWhere('nivel_congestionamento', $operator, '%'.$q.'%')->orWhere('impacto_manutencao', $operator, '%'.$q.'%')))
            ->when($input['distrito_id'] ?? null, fn ($query, $id) => $query->where('distrito_id', $id))
            ->when($sort, fn ($query) => $query->orderBy($sort, $direction))
            ->orderBy('nome')->orderBy('id')->paginate(20)->withQueryString();
        $districts = Distrito::query()->orderBy('nome')->pluck('nome', 'id')->all();
        return view('maps.roads-index', compact('resource', 'items', 'districts', 'sort', 'direction'));
    }

    public function show(int $id, MapData $data)
    {
        $road = DB::table($data->table('mobility', 'via_transitos'))->where('id', $id)->first();
        abort_unless($road, 404);
        $district = $road->distrito_id ? Distrito::query()->find($road->distrito_id)?->nome : null;
        return view('maps.road-show', compact('road', 'district'));
    }

    public function create(RoadResource $catalog)
    {
        $resource = $catalog->get(); $road = null; $values = [];
        return view('maps.road-form', compact('resource', 'road', 'values'));
    }

    public function edit(int $id, MapData $data, RoadResource $catalog)
    {
        $road = DB::table($data->table('mobility', 'via_transitos'))->where('id', $id)->first();
        abort_unless($road, 404);
        $resource = $catalog->get($road);
        $values = (array) $road;
        if ($values['ultima_atualizacao']) $values['ultima_atualizacao'] = \Carbon\Carbon::parse($values['ultima_atualizacao'])->format('Y-m-d\TH:i');
        return view('maps.road-form', compact('resource', 'road', 'values'));
    }

    public function store(Request $request, MapData $data) { return $this->save($request, $data); }
    public function update(Request $request, int $id, MapData $data)
    {
        abort_unless(DB::table($data->table('mobility', 'via_transitos'))->where('id', $id)->exists(), 404);
        return $this->save($request, $data, $id);
    }

    public function destroyBatch(Request $request, MapData $data)
    {
        $table = $data->table('mobility', 'via_transitos');
        $input = $request->validate([
            'selected' => 'required|array|min:1|max:20',
            'selected.*' => ['required', 'integer', 'distinct', Rule::exists($table, 'id')],
        ]);
        $deleted = DB::transaction(fn () => DB::table($table)->whereIn('id', $input['selected'])->delete());
        return redirect()->route($request->routeIs('original-roads.*') ? 'original-roads.index' : 'roads.index')->with('status', "$deleted vias excluídas.");
    }

    public function destroy(Request $request, int $id, MapData $data)
    {
        $deleted = DB::table($data->table('mobility', 'via_transitos'))->where('id', $id)->delete();
        abort_unless($deleted, 404);
        return redirect()->route($request->routeIs('original-roads.*') ? 'original-roads.index' : 'roads.index')->with('status', 'Via excluída.');
    }

    private function save(Request $request, MapData $data, ?int $id = null)
    {
        $existing = $id ? DB::table($data->table('mobility', 'via_transitos'))->where('id', $id)->first() : null;
        $types = array_keys(RoadResource::TYPES);
        $pavements = array_keys(RoadResource::PAVEMENTS);
        if ($existing?->tipo) $types[] = $existing->tipo;
        if ($existing?->estado_pavimento) $pavements[] = $existing->estado_pavimento;
        $input = $request->validate([
            'distrito_id' => ['nullable', 'integer', Rule::exists((new Distrito)->getTable(), 'id')],
            'nome' => 'nullable|string|max:255', 'tipo' => ['nullable', Rule::in($types)],
            'numero_faixas' => 'nullable|integer|min:1', 'sentido' => 'nullable|in:unica,dupla',
            'direcao_fluxo' => ['required', Rule::in(array_keys(RoadResource::DIRECTIONS))],
            'limite_velocidade' => 'nullable|integer|min:1',
            'estado_pavimento' => ['nullable', Rule::in($pavements)],
            'inclinacao_pista' => 'nullable|numeric|between:-90,90',
            'nivel_congestionamento' => 'required|string|max:255',
            'velocidade_media' => 'nullable|integer|min:0', 'volume_veiculos' => 'nullable|integer|min:0',
            'impacto_manutencao' => 'required|in:baixo,medio,alto', 'ultima_atualizacao' => 'required|date',
            'geojson' => 'nullable|string|max:500000',
        ]);
        if (!empty($input['geojson'])) {
            $geometry = json_decode($input['geojson'], true);
            if (!is_array($geometry) || !in_array($geometry['type'] ?? null, ['LineString', 'MultiLineString'], true)) throw ValidationException::withMessages(['geojson' => 'Desenhe um traçado válido.']);
            try { GeoJson::validateGeometry($geometry); }
            catch (\InvalidArgumentException $e) { throw ValidationException::withMessages(['geojson' => $e->getMessage()]); }
        }
        $table = $data->table('mobility', 'via_transitos');
        $input['updated_at'] = now();
        if ($id) DB::table($table)->where('id', $id)->update($input);
        else { $input['created_at'] = now(); $id = DB::table($table)->insertGetId($input); }
        return redirect()->route($request->routeIs('original-roads.*') ? 'original-roads.edit' : 'roads.edit', $id)->with('status', 'Via salva.');
    }
}
