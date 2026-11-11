<?php

namespace App\Http\Controllers\Map;

use App\Http\Controllers\Controller;
use App\Models\Distrito;
use App\Services\{ClimateResource, MapData};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClimateResourceController extends Controller
{
    public function __invoke(string $kind, Request $request, MapData $data, ClimateResource $catalog)
    {
        $resource = $catalog->get($kind);
        $input = $request->validate([
            'q' => 'nullable|string|max:100',
            'distrito_id' => ['nullable', 'integer', Rule::exists((new Distrito)->getTable(), 'id')],
            'sort' => ['nullable', Rule::in(array_keys($resource->getColumns()))],
            'direction' => 'nullable|in:asc,desc',
        ]);
        $table = $data->table('climate', $catalog->table($kind));
        $districtTable = (new Distrito)->getTable();
        $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $sort = $input['sort'] ?? 'timestamp';
        $direction = $input['direction'] ?? 'desc';
        $sortColumn = $sort === 'distrito_nome' ? 'd.nome' : 'c.'.$sort;
        $items = DB::table($table.' as c')->leftJoin($districtTable.' as d', 'd.id', '=', 'c.distrito_id')
            ->when($input['q'] ?? null, fn ($query, $q) => $query->where('d.nome', $operator, '%'.$q.'%'))
            ->when($input['distrito_id'] ?? null, fn ($query, $id) => $query->where('c.distrito_id', $id))
            ->select('c.*', 'd.nome as distrito_nome')
            ->orderBy($sortColumn, $direction)->orderByDesc('c.timestamp')->orderBy('c.distrito_id')
            ->simplePaginate(20)->withQueryString();
        $districts = Distrito::query()->orderBy('nome')->pluck('nome', 'id');
        return view('maps.climate-records', compact('resource', 'items', 'districts', 'kind', 'sort', 'direction'));
    }
}
