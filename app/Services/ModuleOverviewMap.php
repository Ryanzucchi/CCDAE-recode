<?php

namespace App\Services;

use App\Models\Distrito;
use GeoVeil\Layers\{BuildingLayer, LineLayer, PointLayer, PolygonLayer};
use GeoVeil\Map;
use GeoVeil\Sources\GeoJson;
use GeoVeil\Plugins\{Leaflet, MapLibre};
use GeoVeil\Widgets\Stat;

class ModuleOverviewMap
{
    public function __construct(private readonly MapData $data) {}

    public function build(string $module, ?int $districtId, string $engine = 'maplibre'): array
    {
        $layers = [PolygonLayer::make('distritos')->label('Distritos')
            ->source(Distrito::query()->when($districtId, fn ($q) => $q->where('id', $districtId)))
            ->properties(['nome'])->color('#64748b')->popup(['nome' => 'Distrito'])];
        $notices = [];
        $definitions = $module === 'energia' ? [
            ['postes', 'Postes', 'codigo_patrimonio', '#9ca3af', true, null],
            ['equipamentos_infraestrutura', 'Equipamentos', 'nome', '#f59e0b', true, null],
            ['centrais_distribuicao', 'Centrais de distribuição', 'nome', '#dc2626', true, null],
            ['cabeamentos', 'Baixa tensão', 'nome', '#f59e0b', false, 'eletrico_baixa_tensao'],
            ['cabeamentos', 'Alta tensão', 'nome', '#ef4444', false, 'eletrico_alta_tensao'],
        ] : [
            ['antenas', 'Antenas', 'codigo_patrimonio', '#d946ef', true, null],
            ['equipamentos_infraestrutura', 'Equipamentos', 'nome', '#0891b2', true, null],
            ['cabeamentos', 'Fibra óptica', 'nome', '#22d3ee', false, 'fibra_optica'],
            ['cabeamentos', 'Backbone', 'nome', '#1d4ed8', false, 'fibra_backbone'],
            ['cabeamentos', 'Cobre', 'nome', '#ea580c', false, 'cobre_telefonia'],
        ];
        foreach ($definitions as $index => [$tableName, $label, $field, $color, $points, $cableType]) {
            $table = $this->data->table('infrastructure', $tableName);
            if (!$this->data->exists($table)) { $notices[] = "$label: fonte indisponível."; continue; }
            $id = 'infra-'.$index;
            $layer = ($points ? PointLayer::make($id) : LineLayer::make($id))
                ->label($label)->color($color)->popup([$field => $label]);
            if ($engine === 'maplibre') {
                $tileName = match ($tableName) {
                    'equipamentos_infraestrutura' => 'equipamentos',
                    'centrais_distribuicao' => 'centrais',
                    default => $tableName,
                };
                $source = ($module === 'energia' ? 'energia_' : 'internet_').$tileName;
                $filters = [];
                if ($districtId) $filters[] = ['==', ['get', 'distrito_id'], $districtId];
                if ($cableType) $filters[] = ['==', ['get', 'tipo_cabo'], $cableType];
                $layer->vectorTiles(route('maps.tilejson', $source), $source,
                    count($filters) > 1 ? array_merge(['all'], $filters) : ($filters[0] ?? null));
            } else {
                $layer->source(GeoJson::make(['type' => 'FeatureCollection', 'features' => []]))
                    ->viewportGeoJson(route('maps.infrastructure-viewport', [
                        'module' => $module, 'layer' => $index, 'distrito_id' => $districtId,
                    ]));
            }
            $layers[] = $layer;
        }
        if ($engine === 'maplibre') {
            $threeDimensional = $module === 'energia' ? [
                ['energia_postes_3d', 'Postes 3D', '#64748b', 15],
                ['energia_equipamentos_3d', 'Equipamentos 3D', '#f59e0b', 15],
            ] : [
                ['internet_antenas_3d', 'Antenas 3D', '#d946ef', 14],
            ];
            foreach ($threeDimensional as [$source, $label, $color, $zoom]) {
                $filter = $districtId ? ['==', ['get', 'distrito_id'], $districtId] : null;
                $layers[] = BuildingLayer::make('infra-'.$source)->label($label)->color($color)
                    ->height('render_height')->minZoom($zoom)
                    ->popup(['codigo_patrimonio' => 'Patrimônio', 'nome' => 'Nome'])
                    ->vectorTiles(route('maps.tilejson', $source), $source, $filter);
            }
        }
        $map = Map::make('painel-'.$module)->title($module === 'energia' ? 'Infraestrutura elétrica' : 'Infraestrutura de internet')
            ->theme('dark')->layout('map')->plugin($engine === 'leaflet' ? Leaflet::make() : MapLibre::make())
            ->center(-57.681, -16.071)->zoom(12)->layers($layers)->searchable($engine !== 'maplibre')
            ->widgets([Stat::make('Camadas de infraestrutura')->count('distritos')]);
        return [$map, $notices];
    }
}
