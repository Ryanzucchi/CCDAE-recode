<?php

namespace App\Http\Controllers\Map;

use App\Http\Controllers\Controller;
use App\Models\Distrito;
use App\Services\DistrictGeometry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DistrictEditorController extends Controller
{
    public function create() { return view('maps.district-form', ['district' => new Distrito]); }
    public function edit(Distrito $district) { return view('maps.district-form', compact('district')); }

    public function store(Request $request, DistrictGeometry $geometry)
    {
        return $this->save($request, new Distrito, $geometry);
    }

    public function update(Request $request, Distrito $district, DistrictGeometry $geometry)
    {
        return $this->save($request, $district, $geometry);
    }

    public function destroy(Request $request, Distrito $district)
    {
        $district->delete();
        if ($request->boolean('modal')) return view('maps.district-deleted');
        return redirect()->route('districts.index')->with('status', 'Distrito excluído.');
    }

    public function destroyBatch(Request $request)
    {
        $input = $request->validate([
            'selected' => 'required|array|min:1',
            'selected.*' => ['required', 'integer', 'distinct', Rule::exists((new Distrito)->getTable(), 'id')],
        ]);
        $deleted = DB::transaction(fn () => Distrito::query()->whereIn('id', $input['selected'])->delete());
        return redirect()->route('districts.index')->with('status', "$deleted distritos excluídos.");
    }

    private function save(Request $request, Distrito $district, DistrictGeometry $geometry)
    {
        $input = $request->validate(['nome' => 'required|string|max:255', 'cidade' => 'required|string|max:255', 'populacao' => 'nullable|integer|min:0|max:2147483647', 'consumo_medio_kwh' => 'nullable|numeric|min:0|max:999999999', 'geojson' => 'required|string|max:2000000']);
        $input['populacao'] = $input['populacao'] ?? 0;
        DB::transaction(function () use ($input, $district, $geometry) {
            // Serialize boundary edits to avoid two simultaneous overlapping creations.
            if (DB::getDriverName() === 'pgsql') DB::statement('LOCK TABLE geo.distritos IN SHARE ROW EXCLUSIVE MODE');
            $shape = $geometry->prepare($input['geojson'], $district->getKey());
            unset($input['geojson']);
            $district->fill(array_merge($input, $shape))->save();
        });
        return redirect()->route('districts.edit', [$district, 'modal' => $request->boolean('modal') ? 1 : null])->with('status', 'Distrito salvo.');
    }
}
