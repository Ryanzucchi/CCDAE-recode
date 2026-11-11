<?php

namespace App\Http\Controllers\Map;

use App\Http\Controllers\Controller;
use App\Services\MapData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OccurrenceBatchController extends Controller
{
    public function energy(Request $request, MapData $data) { return $this->delete($request, $data, 'energy', 'incidentes_energia', 'energy.incidents.index', 'incidentes de energia'); }
    public function internet(Request $request, MapData $data) { return $this->delete($request, $data, 'internet', 'incidentes_internet', 'internet.incidents.index', 'incidentes de internet'); }
    public function traffic(Request $request, MapData $data) { return $this->delete($request, $data, 'mobility', 'infracoes_transito', 'traffic.infractions.index', 'infrações'); }
    public function energyOne(Request $request, int $id, MapData $data) { return $this->deleteOne($request, $id, $data, 'energy', 'incidentes_energia', 'energy.incidents.index'); }
    public function internetOne(Request $request, int $id, MapData $data) { return $this->deleteOne($request, $id, $data, 'internet', 'incidentes_internet', 'internet.incidents.index'); }
    public function trafficOne(Request $request, int $id, MapData $data) { return $this->deleteOne($request, $id, $data, 'mobility', 'infracoes_transito', 'traffic.infractions.index'); }

    private function delete(Request $request, MapData $data, string $schema, string $name, string $index, string $label)
    {
        $table = $data->table($schema, $name);
        $input = $request->validate([
            'selected' => 'required|array|min:1|max:20',
            'selected.*' => ['required', 'integer', 'distinct', Rule::exists($table, 'id')],
        ]);
        $deleted = DB::transaction(fn () => DB::table($table)->whereIn('id', $input['selected'])->delete());
        $destination = $request->routeIs('original-occurrence.transito.*') ? 'original-occurrence.transito.index' : $index;
        return redirect()->route($destination)->with('status', "$deleted $label excluídos.");
    }

    private function deleteOne(Request $request, int $id, MapData $data, string $schema, string $name, string $index)
    {
        $deleted = DB::table($data->table($schema, $name))->where('id', $id)->delete();
        abort_unless($deleted, 404);
        $destination = $request->routeIs('original-occurrence.transito.*') ? 'original-occurrence.transito.index' : $index;
        return redirect()->route($destination)->with('status', 'Registro excluído.');
    }
}
