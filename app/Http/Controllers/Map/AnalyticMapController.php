<?php

namespace App\Http\Controllers\Map;

use App\Http\Controllers\Controller;
use App\Models\Distrito;
use App\Services\{MapData, TrafficPopup};
use GeoVeil\Layers\{PolygonLayer, PointLayer, LineLayer, BuildingLayer};
use GeoVeil\Map;
use GeoVeil\Plugins\{Leaflet, MapLibre};
use GeoVeil\Sources\GeoJson;
use GeoVeil\Widgets\Stat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnalyticMapController extends Controller
{
    public const METRICS = [
        'temperatura' => ['temperatura_registrada', 'temperatura', 'Temperatura', '°C', [20, 28, 35]],
        'vento' => ['vento_registrado', 'velocidade', 'Velocidade do vento', 'km/h', [10, 20, 40]],
        'chuva' => ['chuva_registrada', 'precipitacao_mm', 'Chuva', 'mm', [1, 10, 30]],
        'radiacao' => ['radiacao_solar', 'radiacao_w_m2', 'Radiação solar', 'W/m²', [200, 500, 800]],
        'uv' => ['indice_uv', 'uv', 'Índice UV', '', [3, 6, 8]],
        'particulas' => ['particulas_ar', 'pm25', 'Partículas PM2.5', 'µg/m³', [15, 35, 75]],
        'pressao' => ['pressao_atmosferica', 'pressao_hpa', 'Pressão atmosférica', 'hPa', [1000, 1013, 1025]],
    ];

    public function __invoke(Request $request, string $page, MapData $data, TrafficPopup $trafficPopup)
    {
        abort_unless(in_array($page, ['clima', 'infraestrutura', 'transito'], true), 404);
        $request->validate(['engine' => 'nullable|in:maplibre,leaflet', 'distrito_id' => ['nullable', 'integer', Rule::in(Distrito::query()->pluck('id')->all())], 'metric' => ['nullable', Rule::in(array_keys(self::METRICS))], 'at' => 'nullable|date_format:Y-m-d H:i:s', 'frame' => 'nullable|boolean']);
        $districtId = $request->integer('distrito_id');
        $districts = Distrito::query()->orderBy('nome')->get(['id', 'nome']);
        $geometry = Distrito::query()->when($districtId, fn ($q) => $q->where('id', $districtId));
        $layers = [PolygonLayer::make('distritos')->label('Distritos')->source($geometry)->properties(['nome'])->popup(['nome' => 'Distrito'])->color('#64748b')];
        $widgets = []; $notices = []; $times = []; $selectedTime = null;
        $metric = $request->input('metric', 'temperatura');
        $titles = ['clima' => 'Central de Monitoramento Climático', 'infraestrutura' => 'Visão Global da Infraestrutura', 'transito' => 'Fluxo de Trânsito Urbano'];
        $title = $titles[$page];
        if ($page === 'infraestrutura') {
            if ($data->exists($data->table('infrastructure', 'osm_buildings'))) {
                $layers[] = BuildingLayer::make('construcoes')->label('Construções 3D')
                    ->colorBy('render_height', [12, 30, 80], ['#d6d3d1', '#a8a29e', '#78716c', '#57534e'])
                    ->minZoom(14)->height('render_height')->viewportGeoJson(route('maps.building-viewport'))
                    ->popup(['name' => 'Construção', 'building' => 'Tipo', 'render_height' => 'Altura']);
            }
            $sources = [
                ['postes', 'Postes', 'codigo_patrimonio', '#9ca3af', true, 15],
                ['antenas', 'Antenas', 'codigo_patrimonio', '#ec4899', true, 13],
                ['centrais_distribuicao', 'Centrais de distribuição', 'nome', '#f59e0b', true, 10],
                ['equipamentos_infraestrutura', 'Equipamentos', 'nome', '#10b981', true, 15],
                ['cabeamentos', 'Cabeamentos', 'nome', '#06b6d4', false, 14],
            ];
            foreach ($sources as $index => [$name, $label, $field, $color, $points, $minZoom]) {
                $table = $data->table('infrastructure', $name);
                if (!$data->exists($table)) { $notices[] = "$label: fonte ainda não disponível."; continue; }
                $layer = ($points ? PointLayer::make($name) : LineLayer::make($name))->label($label)
                    ->color($color)->minZoom($minZoom)->popup([$field => $label, 'estado_conservacao' => 'Conservação']);
                if ($request->input('engine') === 'leaflet') {
                    $layer->source(GeoJson::make(['type' => 'FeatureCollection', 'features' => []]))
                        ->viewportGeoJson(route('maps.infrastructure-viewport', ['module' => 'global', 'layer' => $index, 'distrito_id' => $districtId ?: null]));
                } else {
                    $source = match ($name) {
                        'equipamentos_infraestrutura' => 'equipamentos',
                        'centrais_distribuicao' => 'centrais',
                        default => $name,
                    };
                    $layer->vectorTiles(route('maps.tilejson', $source), $source,
                        $districtId ? ['==', ['get', 'distrito_id'], $districtId] : null);
                }
                $layers[] = $layer;
                $widgets[] = Stat::make($label.' no mapa')->count($name);
            }
            $centralTable = $data->table('infrastructure', 'centrais_distribuicao');
            if ($data->exists($centralTable)) {
                $areas = PolygonLayer::make('centrais-areas')->label('Áreas das centrais')->color('#f59e0b')->minZoom(10)
                    ->popup(['nome' => 'Central', 'estado_conservacao' => 'Conservação']);
                if ($request->input('engine') === 'leaflet') {
                    $areas->source(GeoJson::make(['type' => 'FeatureCollection', 'features' => []]))
                        ->viewportGeoJson(route('maps.infrastructure-viewport', ['module' => 'global', 'layer' => 5, 'distrito_id' => $districtId ?: null]));
                } else {
                    $filter = ['==', ['geometry-type'], 'Polygon'];
                    if ($districtId) $filter = ['all', $filter, ['==', ['get', 'distrito_id'], $districtId]];
                    $areas->vectorTiles(route('maps.tilejson', 'centrais'), 'centrais', $filter);
                }
                $layers[] = $areas;
            }
            $roadTable = $data->table('mobility', 'via_transitos');
            if ($data->exists($roadTable)) {
                $roads = LineLayer::make('vias-infraestrutura')->label('Vias')->color('#22c55e')
                    ->colorByCategory('nivel_congestionamento', ['#22c55e', '#eab308', '#f97316', '#ef4444'], ['livre', 'moderado', 'intenso', 'parado'])->minZoom(8)
                    ->popup(['nome' => 'Via', 'nivel_congestionamento' => 'Congestionamento', 'velocidade_media' => 'Velocidade média']);
                if ($request->input('engine') === 'leaflet') {
                    $roads->source(GeoJson::make(['type' => 'FeatureCollection', 'features' => []]))
                        ->viewportGeoJson(route('maps.infrastructure-viewport', ['module' => 'global', 'layer' => 6, 'distrito_id' => $districtId ?: null]));
                } else {
                    $roads->vectorTiles(route('maps.tilejson', 'vias'), 'vias',
                        $districtId ? ['==', ['get', 'distrito_id'], $districtId] : null);
                }
                $layers[] = $roads;
            }
        } elseif ($page === 'transito') {
            $table = $data->table('mobility', 'via_transitos');
            if ($data->exists($table)) {
                $query = DB::table($table)->when($districtId, fn ($q) => $q->where('distrito_id', $districtId));
                if ($query->count() > 10000) $notices[] = 'Exibindo as primeiras 10.000 vias. Selecione um distrito para detalhar.';
                $rows = $query->orderBy('id')->limit(10000)->get(['id', 'nome', 'geojson', 'velocidade_media', 'nivel_congestionamento', 'direcao_fluxo', 'impacto_manutencao']);
                $history = $data->table('mobility', 'registro_transitos');
                if ($data->exists($history)) {
                    $historyQuery = DB::table($history)->whereIn('via_transito_id', $rows->pluck('id'))->where('timestamp', '>=', now()->subHours(24))->where('timestamp', '<=', now());
                    $hourExpression = DB::connection()->getDriverName() === 'pgsql'
                        ? "to_char(timestamp, 'YYYY-MM-DD HH24:00:00')"
                        : "strftime('%Y-%m-%d %H:00:00', timestamp)";
                    $times = (clone $historyQuery)->selectRaw($hourExpression.' as hour')
                        ->groupByRaw($hourExpression)->orderByDesc('hour')->limit(24)->pluck('hour')->all();
                    $selectedTime = $request->input('at');
                    if ($selectedTime && !in_array($selectedTime, $times, true)) abort(422, 'Horário fora das leituras disponíveis.');
                    if ($selectedTime) {
                        $ranked = (clone $historyQuery)->where('timestamp', '>=', $selectedTime)
                            ->where('timestamp', '<', \Carbon\Carbon::parse($selectedTime)->addHour()->format('Y-m-d H:i:s'))
                            ->select(['via_transito_id', 'velocidade_media', 'indice_congestionamento', 'veiculos_total', 'acidentes_ativos', 'obras_ativas', 'alagamento_ativo', 'origem_dado', 'fonte_dado', 'confianca_dado'])
                            ->selectRaw('row_number() over (partition by via_transito_id order by timestamp desc) as reading_rank');
                        $readings = DB::query()->fromSub($ranked, 'ranked')->where('reading_rank', 1)->get()->keyBy('via_transito_id');
                        $rows = $rows->map(function ($row) use ($readings) {
                            $row->velocidade_media = $readings[$row->id]->velocidade_media ?? null;
                            $reading = $readings[$row->id] ?? null;
                            $index = $reading?->indice_congestionamento;
                            $speed = $reading?->velocidade_media;
                            $row->nivel_congestionamento = $index !== null
                                ? ((int) $index >= 80 ? 'parado' : ((int) $index >= 55 ? 'intenso' : ((int) $index >= 30 ? 'moderado' : 'livre')))
                                : ($speed !== null ? ((float) $speed < 10 ? 'parado' : ((float) $speed < 20 ? 'intenso' : ((float) $speed <= 40 ? 'moderado' : 'livre'))) : null);
                            $row->veiculos_total = $reading?->veiculos_total;
                            $row->acidentes_ativos = $reading?->acidentes_ativos;
                            $row->obras_ativas = $reading?->obras_ativas;
                            $row->alagamento_ativo = $reading ? (bool) $reading->alagamento_ativo : null;
                            $row->origem_dado = $reading?->origem_dado;
                            $row->fonte_dado = $reading?->fonte_dado;
                            $row->confianca_dado = $reading?->confianca_dado;
                            return $row;
                        });
                    }
                }
                $rows = $rows->map(fn ($row) => $trafficPopup->decorate($row));
                $unknown = $rows->filter(fn ($row) => $row->velocidade_media === null);
                $trafficFields = array_merge(['nome', 'velocidade_media', 'nivel_congestionamento', 'direcao_fluxo', 'impacto_manutencao', 'veiculos_total', 'acidentes_ativos', 'obras_ativas', 'alagamento_ativo', 'origem_dado', 'fonte_dado', 'confianca_dado'], array_keys(TrafficPopup::FIELDS));
                $layers[] = LineLayer::make('vias-sem-leitura')->label('Vias sem velocidade informada')->color('#94a3b8')->source($data->features($unknown, $trafficFields))->popup(TrafficPopup::FIELDS);
                $rows = $rows->filter(fn ($row) => $row->velocidade_media !== null);
                $layers[] = LineLayer::make('vias')->label('Fluxo de trânsito')->source($data->features($rows, $trafficFields))->colorBy('velocidade_media', [10, 20, 40], ['#ef4444', '#f97316', '#eab308', '#22c55e'])->flowDirection('direcao_fluxo')->popup(TrafficPopup::FIELDS);
                $widgets[] = Stat::make('Vias no mapa')->count('vias');
            } else $notices[] = 'A fonte de trânsito ainda não está disponível.';
        } else {
            [$name, $field, $label, $unit, $breaks] = self::METRICS[$metric];
            $table = $data->table('climate', $name);
            if ($data->exists($table)) {
                $query = DB::table($table)->where('timestamp', '>=', now()->subHours(24))->where('timestamp', '<=', now())->when($districtId, fn ($q) => $q->where('distrito_id', $districtId));
                $times = (clone $query)->select('timestamp')->distinct()->orderByDesc('timestamp')->limit(24)->pluck('timestamp')->map(fn ($stamp) => \Carbon\Carbon::parse($stamp)->format('Y-m-d H:i:s'))->all();
                $selectedTime = $request->input('at', $times[0] ?? null);
                if ($selectedTime && !in_array($selectedTime, $times, true)) abort(422, 'Horário fora das leituras disponíveis.');
                if ($selectedTime) {
                    $readings = (clone $query)->where('timestamp', $selectedTime)->orderBy('distrito_id')->get(['distrito_id', $field, 'origem_dado', 'fonte_dado'])->keyBy('distrito_id');
                    $rows = (clone $geometry)->get(['id', 'nome', 'geojson'])->filter(fn ($district) => isset($readings[$district->id]) && $readings[$district->id]->$field !== null)->map(function ($district) use ($readings, $field, $selectedTime) {
                        $reading = $readings[$district->id];
                        $district->valor = (float) $reading->$field;
                        $district->origem = $reading->origem_dado;
                        $district->fonte = $reading->fonte_dado;
                        $district->horario = $selectedTime;
                        return $district;
                    });
                    $layers[] = PolygonLayer::make('clima')->label("$label ($unit)")->source($data->features($rows, ['nome', 'valor', 'origem', 'fonte', 'horario']))->colorBy('valor', $breaks, ['#38bdf8', '#22c55e', '#facc15', '#ef4444'])->popup(['nome' => 'Distrito', 'valor' => "$label ($unit)", 'horario' => 'Leitura', 'origem' => 'Origem', 'fonte' => 'Fonte']);
                    $widgets[] = Stat::make('Distritos com leitura')->count('clima');
                } else $notices[] = 'Sem leituras desta variável nas últimas 24 horas. Os limites dos distritos continuam visíveis.';
            } else $notices[] = 'A fonte desta variável climática ainda não está disponível.';
        }
        $map = Map::make($page.'-map')->title($title)->theme('dark')->layout('map')->plugin($request->input('engine') === 'leaflet' ? Leaflet::make() : MapLibre::make())->center(-57.681, -16.071)->zoom(12)->layers($layers)->widgets($widgets);
        if ($page === 'transito' && $request->boolean('frame')) {
            $mapLayers = collect($map->toArray()['layers'])->keyBy('id');
            return response()->json(['at' => $selectedTime, 'times' => $times, 'vias' => $mapLayers->get('vias')['data']['features'] ?? [], 'viasSemLeitura' => $mapLayers->get('vias-sem-leitura')['data']['features'] ?? []]);
        }
        return view('maps.analytic', compact('map', 'page', 'title', 'districts', 'districtId', 'notices', 'times', 'selectedTime', 'metric'));
    }
}
