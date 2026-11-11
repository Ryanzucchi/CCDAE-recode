<?php

namespace Tests\Feature;

use App\Models\Distrito;
use App\Models\User;
use GeoVeil\Layers\PolygonLayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistrictMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_district_navigation_and_search_are_local(): void
    {
        foreach (['Centro', 'Jardim'] as $name) {
            Distrito::query()->create(['nome' => $name, 'cidade' => 'Caceres', 'populacao' => 100, 'geojson' => ['type' => 'Polygon', 'coordinates' => [[[0,0],[1,0],[1,1],[0,0]]]]]);
        }
        $this->get(route('maps.districts'))->assertOk()->assertSee(route('original-admin.districts'));
        $this->get(route('districts.index'))->assertOk()->assertSee('Escolha o seu foco');
        $this->get(route('districts.index', ['q' => 'Centro']))->assertOk()
            ->assertSee('Centro')->assertDontSee('Jardim');
        $this->get(route('districts.index', ['q' => 'Inexistente']))->assertOk()
            ->assertSee('Nenhum distrito encontrado');
        $this->get(route('original-admin.districts', ['q' => 'Centro']))->assertOk()
            ->assertSee(route('original-admin.districts'))
            ->assertSee(route('original-admin.districts', ['q' => 'Centro', 'sort' => 'nome', 'direction' => 'desc']));
        $this->get(route('original-admin.districts', ['cidade' => 'Caceres', 'q' => 'Centro', 'sort' => 'nome', 'direction' => 'desc']))->assertOk()
            ->assertSee('name="sort" value="nome"', false)
            ->assertSee('name="direction" value="desc"', false)
            ->assertSee(route('original-admin.districts', ['cidade' => 'Caceres', 'sort' => 'nome', 'direction' => 'desc']));
    }

    public function test_city_filter_shapes_only_the_map_like_original(): void
    {
        foreach (['Cáceres', 'Cuiabá'] as $city) {
            Distrito::query()->create(['nome' => 'Centro '.$city, 'cidade' => $city, 'populacao' => 800,
                'geojson' => ['type' => 'Polygon', 'coordinates' => [[[0,0],[1,0],[1,1],[0,0]]]],
            ]);
        }
        $this->get(route('districts.index', ['cidade' => 'Cáceres']))->assertOk()
            ->assertViewHas('map', function ($map) {
                $payload = $map->toArray();
                $layer = $payload['layers'][0];
                $features = $layer['data']['features'];
                return count($features) === 1 && $features[0]['properties']['cidade'] === 'Cáceres'
                    && $layer['categories']['field'] === 'id' && count($layer['categories']['colors']) === 7
                    && $payload['fitOnLoad'] === true && $payload['fitOnSelect'] === false && $payload['layout'] === 'embedded';
            })->assertSee('2 distritos encontrados')->assertSee('Centro Cuiabá');
        $this->get(route('districts.index'))->assertOk()->assertSee('Escolha o seu foco')
            ->assertViewHas('map', fn ($map) => $map->toArray()['layers'][0]['data']['features'] === []);
    }

    public function test_missing_district_polygons_use_voronoi_cells_or_a_point(): void
    {
        Distrito::query()->create(['nome' => 'Norte', 'cidade' => 'Teste', 'latitude' => -16.0, 'longitude' => -57.7, 'geojson' => []]);
        Distrito::query()->create(['nome' => 'Sul', 'cidade' => 'Teste', 'latitude' => -16.2, 'longitude' => -57.7, 'geojson' => []]);
        $this->get(route('districts.index', ['cidade' => 'Teste']))->assertOk()->assertViewHas('map', function ($map) {
            $layers = collect($map->toArray()['layers']);
            $cells = $layers->firstWhere('id', 'distritos')['data']['features'];
            return count($cells) === 2 && $cells[0]['geometry']['type'] === 'Polygon'
                && $cells[1]['geometry']['type'] === 'Polygon'
                && $cells[0]['properties']['nome'] === 'Norte'
                && $layers->firstWhere('id', 'distritos-sem-area')['data']['features'] === [];
        });
        Distrito::query()->where('nome', 'Sul')->delete();
        $this->get(route('districts.index', ['cidade' => 'Teste']))->assertOk()->assertViewHas('map', function ($map) {
            $layers = collect($map->toArray()['layers']);
            return $layers->firstWhere('id', 'distritos')['data']['features'] === []
                && $layers->firstWhere('id', 'distritos-sem-area')['data']['features'][0]['geometry']['type'] === 'Point';
        });
    }

    public function test_city_map_assigns_the_original_palette_in_district_order(): void
    {
        $first = Distrito::query()->create(['nome' => 'Leste', 'cidade' => 'Teste', 'latitude' => -16.0, 'longitude' => -57.7, 'geojson' => []]);
        $second = Distrito::query()->create(['nome' => 'Oeste', 'cidade' => 'Teste', 'latitude' => -16.1, 'longitude' => -57.8, 'geojson' => []]);
        $this->get(route('districts.index', ['cidade' => 'Teste']))->assertOk()->assertViewHas('map', function ($map) use ($first, $second) {
            $layers = $map->toArray()['layers'];
            return $layers[0]['categories']['orderedValues'] === [$first->id, $second->id]
                && $layers[1]['categories']['orderedValues'] === [$first->id, $second->id]
                && $layers[0]['categories']['colors'][0] === '#06b6d4'
                && $layers[0]['categories']['colors'][1] === '#8b5cf6';
        });
    }

    public function test_selected_district_exposes_the_original_edit_action_to_master(): void
    {
        config(['ccdae.master_email' => 'master@example.test']);
        Distrito::query()->create(['nome' => 'Centro', 'cidade' => 'Teste', 'latitude' => -16.0, 'longitude' => -57.7, 'geojson' => []]);
        $this->get(route('districts.index', ['cidade' => 'Teste']))->assertOk()
            ->assertViewHas('map', fn ($map) => $map->toArray()['layers'][1]['recordAction'] === null);
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']))
            ->get(route('districts.index', ['cidade' => 'Teste']))->assertOk()
            ->assertViewHas('map', fn ($map) => $map->toArray()['layers'][1]['recordAction'] === [
                'label' => 'Editar Distrito', 'url' => '/distritos/{id}/editar', 'modal' => true,
            ]);
    }

    public function test_district_table_exposes_coordinates_and_sorts_like_original(): void
    {
        Distrito::query()->create(['nome' => 'Norte', 'cidade' => 'Teste', 'latitude' => -16.0, 'longitude' => -57.7, 'geojson' => []]);
        Distrito::query()->create(['nome' => 'Sul', 'cidade' => 'Teste', 'latitude' => -16.2, 'longitude' => -57.7, 'geojson' => []]);
        $this->get(route('districts.index', ['sort' => 'latitude', 'direction' => 'asc']))->assertOk()
            ->assertSee('Latitude')->assertSee('Longitude')->assertDontSee('>População</th>', false)
            ->assertViewHas('districts', fn ($districts) => $districts->items()[0]->nome === 'Sul');
        $this->get(route('districts.index', ['sort' => 'latitude', 'direction' => 'desc']))->assertOk()
            ->assertViewHas('districts', fn ($districts) => $districts->items()[0]->nome === 'Norte');
    }

    public function test_district_map_is_rendered_with_geoveil_payload(): void
    {
        Distrito::query()->create([
            'nome' => 'Centro',
            'cidade' => 'Caceres',
            'populacao' => 32100,
            'area_km2' => 5.2,
            'geojson' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [-57.70, -16.07],
                    [-57.68, -16.07],
                    [-57.68, -16.05],
                    [-57.70, -16.05],
                    [-57.70, -16.07],
                ]],
            ],
        ]);

        $this->get(route('maps.districts'))
            ->assertOk()
            ->assertSee('População por distrito')
            ->assertSee('districts-map')
            ->assertSee('Centro');
    }

    public function test_leaflet_uses_the_same_dataset_contract(): void
    {
        $response = $this->get(route('maps.districts', ['engine' => 'leaflet']))->assertOk();
        $response->assertViewHas('map', fn ($map) => $map->toArray()['plugin']['id'] === 'leaflet');
    }

    public function test_source_preserves_query_limit_and_exposes_only_declared_properties(): void
    {
        foreach (['A', 'B'] as $name) {
            Distrito::query()->create([
                'nome' => $name, 'cidade' => 'Teste', 'populacao' => 10,
                'geojson' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [1, 0], [1, 1], [0, 0]]]],
            ]);
        }
        $query = Distrito::query()->orderBy('id')->limit(1);
        $layer = PolygonLayer::make('districts')->source($query)->properties(['nome']);
        $data = $layer->jsonSerialize();
        $this->assertCount(1, $data['data']['features']);
        $this->assertSame(['nome' => 'A'], $data['data']['features'][0]['properties']);
        $this->assertSame(1, $query->getQuery()->limit);
    }

    public function test_payload_cannot_break_out_of_the_json_script_element(): void
    {
        Distrito::query()->create([
            'nome' => '</script><script>alert(1)</script>', 'cidade' => 'Teste', 'populacao' => 10,
            'geojson' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [1, 0], [1, 1], [0, 0]]]],
        ]);
        $this->get(route('maps.districts'))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }
}
