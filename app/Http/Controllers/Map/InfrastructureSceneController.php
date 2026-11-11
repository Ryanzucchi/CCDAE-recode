<?php

namespace App\Http\Controllers\Map;

use App\Http\Controllers\Controller;
use App\Models\Distrito;
use App\Services\MapData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InfrastructureSceneController extends Controller
{
    public function __invoke(Request $request, MapData $data)
    {
        $input = $request->validate(['distrito_id' => 'required|integer']);
        $id = (int) $input['distrito_id'];
        abort_unless(Distrito::query()->whereKey($id)->exists(), 404);
        $items = [
            'postes' => ['infrastructure', 'postes', ['id', 'latitude', 'longitude', 'codigo_patrimonio', 'material', 'estado_conservacao', 'altura_metros', 'resistencia_kg', 'possui_iluminacao', 'data_instalacao', 'ultima_manutencao'], 20000],
            'antenas' => ['infrastructure', 'antenas', ['id', 'latitude', 'longitude', 'codigo_patrimonio', 'tipo_sinal', 'estado_conservacao'], 1000],
            'centrais' => ['infrastructure', 'centrais_distribuicao', ['id', 'latitude', 'longitude', 'geojson', 'nome', 'tipo', 'estado_conservacao'], 500],
            'equipamentos' => ['infrastructure', 'equipamentos_infraestrutura', ['id', 'latitude', 'longitude', 'nome', 'codigo_patrimonio', 'tipo', 'modelo', 'estado_conservacao', 'poste_id', 'central_id', 'data_instalacao', 'ultima_manutencao'], 20000],
            'cabeamentos' => ['infrastructure', 'cabeamentos', ['id', 'geojson', 'nome', 'codigo_patrimonio', 'tipo_cabo', 'capacidade', 'revestimento', 'subterraneo', 'extensao_metros', 'estado_conservacao', 'distrito_id', 'poste_origem_id', 'poste_destino_id', 'central_origem_id', 'central_destino_id'], 40000],
            'vias' => ['mobility', 'via_transitos', ['id', 'geojson', 'nome', 'nivel_congestionamento', 'velocidade_media'], 5000],
        ];
        $result = [];
        foreach ($items as $key => [$schema, $name, $columns, $limit]) {
            $table = $data->table($schema, $name);
            if (!$data->exists($table)) { $result[$key] = []; continue; }
            $query = DB::table($table);
            if ($key === 'cabeamentos') {
                $postes = $data->table('infrastructure', 'postes');
                $centrais = $data->table('infrastructure', 'centrais_distribuicao');
                $hasPostes = $data->exists($postes); $hasCentrais = $data->exists($centrais);
                $query->where(function ($q) use ($id, $postes, $centrais, $hasPostes, $hasCentrais) {
                    $q->where('distrito_id', $id);
                    if ($hasPostes) {
                        $q->orWhereIn('poste_origem_id', DB::table($postes)->select('id')->where('distrito_id', $id))
                            ->orWhereIn('poste_destino_id', DB::table($postes)->select('id')->where('distrito_id', $id));
                    }
                    if ($hasCentrais) {
                        $q->orWhereIn('central_origem_id', DB::table($centrais)->select('id')->where('distrito_id', $id))
                            ->orWhereIn('central_destino_id', DB::table($centrais)->select('id')->where('distrito_id', $id));
                    }
                });
            } else $query->where('distrito_id', $id);
            $result[$key] = $query->orderBy('id')->limit($limit)->get($columns);
        }
        return response()->json($result);
    }
}
