<?php

namespace Tests\Unit;

use GeoVeil\Filters\SelectFilter;
use GeoVeil\Layers\BuildingLayer;
use GeoVeil\Layers\PolygonLayer;
use GeoVeil\Layers\LineLayer;
use GeoVeil\Layers\SurfaceLayer;
use GeoVeil\Map;
use GeoVeil\Plugins\Leaflet;
use GeoVeil\Plugins\MapLibre;
use GeoVeil\Sources\GeoJson;
use GeoVeil\Widgets\Stat;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class GeoVeilTest extends TestCase
{
    private function source(): GeoJson
    {
        return GeoJson::make(['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'id' => 1, 'geometry' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [1, 0], [1, 1], [0, 0]]]], 'properties' => ['name' => 'A', 'population' => 200]],
        ]]);
    }

    public function test_same_map_can_use_either_engine_without_changing_layers(): void
    {
        $map = Map::make('districts')->layers([PolygonLayer::make('districts')->source($this->source())->colorBy('population')]);
        $first = $map->plugin(MapLibre::make())->toArray();
        $second = $map->plugin(Leaflet::make())->toArray();
        $this->assertSame($first['layers'], $second['layers']);
        $this->assertSame('leaflet', $second['plugin']['id']);
    }

    public function test_unsupported_capabilities_fail_before_rendering(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('não suporta');
        Map::make('map')->plugin(Leaflet::make())->layers([BuildingLayer::make('buildings')->source($this->source())])->toArray();
    }

    public function test_duplicate_layers_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $layer = PolygonLayer::make('same')->source($this->source());
        Map::make('map')->plugin(MapLibre::make())->layers([$layer, $layer])->toArray();
    }

    public function test_invalid_scale_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PolygonLayer::make('districts')->colorBy('population', [20, 10]);
    }

    public function test_unknown_filter_layer_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Map::make('map')->plugin(MapLibre::make())->filters([SelectFilter::make('city')->layer('missing')])->toArray();
    }

    public function test_widget_requires_a_data_source(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Map::make('map')->plugin(MapLibre::make())->widgets([Stat::make('Count')])->toArray();
    }

    public function test_duplicate_feature_ids_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $data = $this->source()->jsonSerialize();
        $data['features'][] = $data['features'][0];
        GeoJson::make($data);
    }

    public function test_surface_layer_serializes_controls_and_rejects_leaflet(): void
    {
        $layer = SurfaceLayer::make('population')->source($this->source())->value('population')->altitude(1850);
        $map = Map::make('map')->plugin(MapLibre::make())->theme('dark')->layout('map')->layers([$layer]);
        $this->assertSame('surface', $map->toArray()['layers'][0]['type']);
        $this->assertSame(1850.0, $map->toArray()['layers'][0]['surface']['altitude']);
        $this->expectException(InvalidArgumentException::class);
        $map->plugin(Leaflet::make())->toArray();
    }

    public function test_flow_direction_is_an_opt_in_line_layer_capability(): void
    {
        $source = GeoJson::make(['type' => 'FeatureCollection', 'features' => [[
            'type' => 'Feature', 'id' => 1,
            'geometry' => ['type' => 'LineString', 'coordinates' => [[0, 0], [1, 1]]],
            'properties' => ['direcao_fluxo' => 'fim_inicio'],
        ]]]);
        $layer = LineLayer::make('vias')->source($source)->flowDirection('direcao_fluxo');
        $this->assertSame('direcao_fluxo', $layer->jsonSerialize()['flowDirection']);
        $this->expectException(InvalidArgumentException::class);
        PolygonLayer::make('distritos')->flowDirection('direcao_fluxo');
    }

    public function test_vector_tiles_are_lazy_and_require_maplibre(): void
    {
        $layer = \GeoVeil\Layers\PointLayer::make('postes')->vectorTiles('/tiles/energia_postes', 'energia_postes');
        $map = Map::make('network')->plugin(MapLibre::make())->layers([$layer]);
        $this->assertSame([], $map->toArray()['layers'][0]['data']['features']);
        $this->assertSame('energia_postes', $map->toArray()['layers'][0]['vector']['sourceLayer']);
        $this->expectException(InvalidArgumentException::class);
        $map->plugin(Leaflet::make())->toArray();
    }
}
