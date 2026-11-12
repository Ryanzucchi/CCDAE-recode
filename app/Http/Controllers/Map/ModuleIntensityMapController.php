<?php

namespace App\Http\Controllers\Map;

use App\Http\Controllers\Controller;
use App\Models\Distrito;
use App\Services\{MapData, DistrictMetricCells};
use GeoVeil\Layers\{PointLayer, PolygonLayer, SurfaceLayer};
use GeoVeil\Map;
use GeoVeil\Plugins\{Leaflet, MapLibre};
use GeoVeil\Sources\GeoJson;
use GeoVeil\Widgets\Stat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ModuleIntensityMapController extends Controller
{
    private const TYPES = [
        'transito' => [
            'infracoes' => ['Mapa de infrações', 'mobility', 'infracoes_transito', 1350, 0.003],
            'volume' => ['Mapa de volume de veículos', 'mobility', 'registro_transitos', 1800, 0.003],
            'acidentes' => ['Mapa de acidentes', 'mobility', 'registro_transitos', 1550, 0.0028],
            'obras' => ['Mapa de obras', 'mobility', 'registro_transitos', 1450, 0.0028],
        ],
        'energia' => [
            'densidade' => ['Densidade da rede elétrica', 'infrastructure', 'postes', 1450, 0.0018],
            'consumo' => ['Consumo médio estimado', 'geo', 'distritos', 1750, 0.005],
            'populacao' => ['População', 'geo', 'distritos', 1850, 0.005],
        ],
        'internet' => [
            'densidade-rede' => ['Densidade da rede de internet', 'infrastructure', 'antenas', 1550, 0.0022],
            'demanda' => ['Demanda potencial de internet', 'geo', 'distritos', 1750, 0.005],
        ],
    ];

    public function __invoke(Request $request, string $module, string $type, MapData $data, DistrictMetricCells $metrics)
    {
        abort_unless(isset(self::TYPES[$module][$type]), 404);
        $input = $request->validate([
            'engine' => 'nullable|in:maplibre,leaflet',
            'distrito_id' => ['nullable', 'integer', Rule::exists((new Distrito)->getTable(), 'id')],
            'inicio' => 'nullable|date', 'fim' => $request->input('periodo') === 'hour' ? 'nullable|date' : 'nullable|date|after:inicio',
            'periodo' => 'nullable|in:range,hour',
        ]);
        [$title, $schema, $name, $altitude, $influence] = self::TYPES[$module][$type];
        $districtId = isset($input['distrito_id']) ? (int) $input['distrito_id'] : null;
        $districts = Distrito::query()->orderBy('nome')->get(['id', 'nome']);
        $end = isset($input['fim']) ? \Carbon\Carbon::parse($input['fim']) : now();
        $start = isset($input['inicio']) ? \Carbon\Carbon::parse($input['inicio']) : $end->copy()->subHours($module === 'transito' ? 24 : 720);
        if ($module === 'transito' && ($input['periodo'] ?? 'range') === 'hour') $end = $start->copy()->addHour();
        if ($module === 'transito' && ($start->greaterThanOrEqualTo($end) || $start->diffInDays($end) > 31)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['inicio' => 'O período deve ter até 31 dias e a data inicial deve ser anterior à final.']);
        }
        $table = $data->table($schema, $name);
        $cells = [];
        $gridSize = $module === 'transito' ? ($type === 'infracoes' ? 0.003 : 0.0014) : 0.00115;
        $notices = [];
        $add = function (float $lng, float $lat, ?string $name = null) use (&$cells, $gridSize, $module): void {
            if (!is_finite($lat) || !is_finite($lng) || abs($lat) > 90 || abs($lng) > 180) return;
            $key = ($module === 'transito' ? round($lng / $gridSize) : floor($lng / $gridSize)).':'.($module === 'transito' ? round($lat / $gridSize) : floor($lat / $gridSize));
            $cells[$key] ??= ['lat' => 0.0, 'lng' => 0.0, 'count' => 0];
            $cells[$key]['lat'] += $lat;
            $cells[$key]['lng'] += $lng;
            $cells[$key]['count']++;
            if ($name !== null && $name !== '') $cells[$key]['names'][$name] = true;
        };
        if (($module === 'energia' && in_array($type, ['consumo', 'populacao'], true)) || ($module === 'internet' && $type === 'demanda')) {
            $cells = $metrics->cells($type, $districtId);
        } elseif ($module === 'transito' && $type !== 'infracoes') {
            if ($data->exists($table)) $cells = $this->trafficCells($data, $type, $districtId, $start, $end, $gridSize);
            else $notices[] = 'A fonte de dados ainda não está disponível.';
        } elseif ($data->exists($table)) {
            $query = DB::table($table)->whereNotNull('latitude')->whereNotNull('longitude')
                ->when($districtId, fn ($q) => $q->where('distrito_id', $districtId));
            if ($module === 'transito') $query->whereBetween('ocorrido_em', [$start, $end]);
            $fields = $module === 'transito' ? ['latitude', 'longitude', 'tipo'] : ['latitude', 'longitude'];
            foreach ($query->orderBy('id')->select($fields)->cursor() as $row) $add((float) $row->longitude, (float) $row->latitude, $module === 'transito' ? ($row->tipo ?? null) : null);
        } else $notices[] = 'A fonte de dados ainda não está disponível.';
        if ($module === 'internet' && $type === 'densidade-rede') {
            $equipment = $data->table('infrastructure', 'equipamentos_infraestrutura');
            if ($data->exists($equipment)) {
                $query = DB::table($equipment)->whereNotNull('latitude')->whereNotNull('longitude')
                    ->when($districtId, fn ($q) => $q->where('distrito_id', $districtId));
                foreach ($query->orderBy('id')->select(['latitude', 'longitude'])->cursor() as $row) $add((float) $row->longitude, (float) $row->latitude);
            } else $notices[] = 'Equipamentos: fonte ainda não disponível.';
            $cables = $data->table('infrastructure', 'cabeamentos');
            if ($data->exists($cables)) {
                $query = DB::table($cables)->whereIn('tipo_cabo', ['fibra_optica', 'fibra_backbone', 'cobre_telefonia'])
                    ->when($districtId, fn ($q) => $q->where('distrito_id', $districtId));
                if (DB::connection()->getDriverName() === 'pgsql' && \Illuminate\Support\Facades\Schema::hasColumn($cables, 'geom')) {
                    $query->whereNotNull('geom')->selectRaw('ST_AsGeoJSON(ST_SimplifyPreserveTopology(geom, 0.00003)) AS geometry');
                } else $query->whereNotNull('geojson')->selectRaw('geojson AS geometry');
                foreach ($query->orderBy('id')->cursor() as $row) {
                    $geometry = is_string($row->geometry) ? json_decode($row->geometry, true) : (array) $row->geometry;
                    $lines = match ($geometry['type'] ?? null) {
                        'LineString' => [$geometry['coordinates']], 'MultiLineString' => $geometry['coordinates'], default => []
                    };
                    foreach ($lines as $line) for ($i = 1; $i < count($line); $i++) {
                        [$x1, $y1] = $line[$i - 1]; [$x2, $y2] = $line[$i];
                        $distance = hypot(($x2 - $x1) * cos(deg2rad(($y1 + $y2) / 2)) * 111320, ($y2 - $y1) * 110540);
                        $steps = max(1, (int) ceil($distance / 90));
                        for ($step = 0; $step < $steps; $step++) {
                            $ratio = $step / $steps;
                            $add($x1 + ($x2 - $x1) * $ratio, $y1 + ($y2 - $y1) * $ratio);
                        }
                    }
                }
            } else $notices[] = 'Cabeamentos: fonte ainda não disponível.';
        }
        usort($cells, fn (array $a, array $b) => ($b['value'] ?? $b['count']) <=> ($a['value'] ?? $a['count']));
        $features = [];
        foreach ($cells as $index => $cell) {
            $features[] = ['type' => 'Feature', 'id' => $index + 1,
                'geometry' => ['type' => 'Point', 'coordinates' => [$cell['lng'] / $cell['count'], $cell['lat'] / $cell['count']]],
                'properties' => [
                    'value' => $cell['value'] ?? $cell['count'],
                    'name' => $cell['name'] ?? (!empty($cell['names']) ? implode(', ', array_slice(array_keys($cell['names']), 0, 3)) : ($module === 'transito' ? $cell['count'].' ponto(s) nesta área' : 'Ativos nesta área')),
                    'streets' => $cell['streets'] ?? (isset($cell['names']) ? count($cell['names']) : 0),
                    'samples' => $cell['count'],
                ]];
        }
        if (!$features) $notices[] = $module === 'transito' ? 'Nenhum dado de trânsito com localização no período selecionado.' : 'Nenhum ativo com localização disponível.';
        $source = GeoJson::make(['type' => 'FeatureCollection', 'features' => $features]);
        $leaflet = ($input['engine'] ?? null) === 'leaflet';
        $colors = match ($type) {
            'volume' => ['#e0f2fe', '#38bdf8', '#2563eb', '#312e81'],
            'acidentes' => ['#ffedd5', '#fb923c', '#ef4444', '#7f1d1d'],
            'obras' => ['#ecfccb', '#84cc16', '#ca8a04', '#713f12'],
            'consumo' => ['#dbeafe', '#38bdf8', '#7c3aed', '#4c1d95'],
            'populacao' => ['#dcfce7', '#22c55e', '#0f766e', '#164e63'],
            'demanda' => ['#ecfeff', '#06b6d4', '#7c3aed', '#4c1d95'],
            default => ['#fde68a', '#fb923c', '#ef4444', '#7f1d1d'],
        };
        $layer = $leaflet
            ? PointLayer::make('intensidade')->label($title)->source($source)->colorBy('value', [2, 5, 10], $colors)->popup(['name' => 'Área', 'value' => 'Valor'])
            : SurfaceLayer::make('intensidade')->label($title)->source($source)->value('value')->altitude($altitude)->influence($influence)->popup(['name' => 'Área', 'value' => 'Valor']);
        $districtLayer = PolygonLayer::make('distritos')->label('Distritos')->source(Distrito::query()->when($districtId, fn ($q) => $q->where('id', $districtId)))->properties(['nome'])->color('#64748b')->popup(['nome' => 'Distrito']);
        $map = Map::make($module.'-'.$type)->title($title)->theme('dark')->layout('embedded')
            ->plugin($leaflet ? Leaflet::make() : MapLibre::make())->center(-57.6875, -16.07083)->zoom(12)
            ->layers([$districtLayer, $layer])->widgets([Stat::make($module === 'transito' ? 'Áreas com infrações' : 'Áreas com ativos')->count('intensidade')]);
        return view('maps.module-intensity', compact('map', 'module', 'type', 'title', 'districts', 'districtId', 'notices', 'start', 'end'));
    }

    private function trafficCells(MapData $data, string $type, ?int $districtId, \Carbon\Carbon $start, \Carbon\Carbon $end, float $gridSize): array
    {
        $roads = $data->table('mobility', 'via_transitos');
        if (!$data->exists($roads)) return [];
        $metric = ['volume' => 'veiculos_total', 'acidentes' => 'acidentes_ativos', 'obras' => 'obras_ativas'][$type];
        $summary = DB::table($data->table('mobility', 'registro_transitos').' as r')
            ->join($roads.' as v', 'v.id', '=', 'r.via_transito_id')
            ->whereBetween('r.timestamp', [$start, $end])
            ->when($districtId, fn ($q) => $q->where('v.distrito_id', $districtId))
            ->whereNotNull('v.geojson')
            ->groupBy('v.id', 'v.nome', 'v.geojson')
            ->select('v.id', 'v.nome', 'v.geojson')
            ->selectRaw(($type === 'volume' ? 'AVG' : 'SUM').'(r.'.$metric.') as metric')
            ->cursor();
        $cells = [];
        foreach ($summary as $road) {
            $value = (float) ($road->metric ?? 0);
            $geometry = is_string($road->geojson) ? json_decode($road->geojson, true) : (array) $road->geojson;
            if (($geometry['type'] ?? null) === 'Feature') $geometry = $geometry['geometry'] ?? [];
            $lines = match ($geometry['type'] ?? null) {
                'LineString' => [$geometry['coordinates']], 'MultiLineString' => $geometry['coordinates'], default => [],
            };
            foreach ($lines as $line) for ($i = 1; $i < count($line); $i++) {
                [$x1, $y1] = $line[$i - 1]; [$x2, $y2] = $line[$i];
                $distance = hypot(($x2 - $x1) * cos(deg2rad(($y1 + $y2) / 2)) * 111320, ($y2 - $y1) * 110540);
                $steps = max(1, (int) ceil($distance / 180));
                for ($step = 0; $step < $steps; $step++) {
                    $ratio = $step / $steps;
                    $lng = $x1 + ($x2 - $x1) * $ratio; $lat = $y1 + ($y2 - $y1) * $ratio;
                    if (!is_finite($lng) || !is_finite($lat) || abs($lat) > 90 || abs($lng) > 180) continue;
                    $key = round($lng / $gridSize).':'.round($lat / $gridSize);
                    $cells[$key] ??= ['lat' => 0.0, 'lng' => 0.0, 'value' => 0.0, 'count' => 0, 'names' => []];
                    $cells[$key]['lat'] += $lat; $cells[$key]['lng'] += $lng;
                    $cells[$key]['value'] += $value; $cells[$key]['count']++;
                    $cells[$key]['names'][$road->nome ?: 'Via'] = true;
                }
            }
        }
        foreach ($cells as &$cell) {
            $cell['value'] = $type === 'volume' ? round($cell['value'] / $cell['count'], 2) : $cell['value'] / $cell['count'];
            $cell['name'] = implode(', ', array_slice(array_keys($cell['names']), 0, 3));
            $cell['streets'] = count($cell['names']);
            unset($cell['names']);
        }
        return $cells;
    }

}
