<?php

namespace App\Http\Controllers\Map;

use App\Http\Controllers\Controller;
use App\Models\Distrito;
use App\Services\{MapData, ModuleOverviewMap};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ModuleController extends Controller
{
    public function __invoke(Request $request, MapData $data, ModuleOverviewMap $overview)
    {
        $module = $request->route()->getName();
        $districts = Distrito::query()->orderBy('nome')->get(['id', 'nome']);
        $request->validate(['distrito_id' => ['nullable', 'integer', Rule::in($districts->pluck('id')->all())], 'engine' => 'nullable|in:maplibre,leaflet']);
        $districtId = $request->integer('distrito_id');
        $readingTime = null;
        $query = function ($schema, $table) use ($data, $districtId) {
            $table = $data->table($schema, $table);
            return $data->exists($table) ? DB::table($table)->when($districtId, fn ($q) => $q->where('distrito_id', $districtId)) : null;
        };
        if ($module === 'energia' || $module === 'internet') {
            $energy = $module === 'energia';
            $network = $query('infrastructure', 'cabeamentos');
            if ($network) $energy ? $network->where('tipo_cabo', 'like', 'eletrico_%') : $network->whereIn('tipo_cabo', ['fibra_optica', 'fibra_backbone', 'cobre_telefonia']);
            $incidents = $query($energy ? 'energy' : 'internet', $energy ? 'incidentes_energia' : 'incidentes_internet');
            $stats = [
                ($energy ? 'Postes cadastrados' : 'Antenas cadastradas') => $query('infrastructure', $energy ? 'postes' : 'antenas')?->count(),
                ($energy ? 'Trechos elétricos' : 'Trechos de telecom') => $network?->count(),
                'Incidentes em aberto' => $incidents?->whereNotIn('situacao', ['resolvido', 'cancelado'])->count(),
            ];
            [$map, $notices] = $overview->build($module, $districtId ?: null, $request->input('engine', 'maplibre'));
        } else {
            $history = $data->table('mobility', 'registro_transitos');
            $reading = $data->exists($history) ? DB::table($history.' as r')
                ->when($districtId, fn ($q) => $q->join($data->table('mobility', 'via_transitos').' as v', 'v.id', '=', 'r.via_transito_id')->where('v.distrito_id', $districtId))
                ->orderByDesc('r.timestamp')->first(['r.veiculos_total', 'r.timestamp']) : null;
            $readingTime = $reading?->timestamp ? \Carbon\Carbon::parse($reading->timestamp)->format('d/m/Y H:i') : null;
            $stats = [
                'Vias monitoradas' => $query('mobility', 'via_transitos')?->count(),
                'Veículos na última leitura' => $reading?->veiculos_total,
                'Infrações em 30 dias' => $query('mobility', 'infracoes_transito')?->where('ocorrido_em', '>=', now()->subDays(30))->count(),
            ];
            $map = null; $notices = [];
        }
        $title = ['energia' => 'Energia', 'internet' => 'Internet', 'transito' => 'Trânsito e Mobilidade'][$module];
        return view('maps.module', compact('module', 'title', 'stats', 'districts', 'districtId', 'map', 'notices', 'readingTime'));
    }
}
