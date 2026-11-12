<?php
namespace Tests\Feature;

use App\Models\Distrito;
use GeoVeil\Geometry\PolygonSampler;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ModuleIntensityMapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['infracoes_transito', 'postes', 'antenas'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id(); $table->unsignedBigInteger('distrito_id')->nullable();
                $table->float('latitude')->nullable(); $table->float('longitude')->nullable();
                $table->timestamp('ocorrido_em')->nullable();
                if ($name === 'infracoes_transito') $table->string('tipo')->nullable();
            });
        }
    }

    public function test_original_filament_map_slugs_open_the_corresponding_geoveil_pages(): void
    {
        foreach ([
            'energia/mapa-energia' => 'Densidade da rede elétrica',
            'energia/mapa-consumo' => 'Consumo médio estimado',
            'internet/mapa-rede' => 'Densidade da rede de internet',
            'internet/mapa-demanda' => 'Demanda potencial de internet',
            'transito/mapa-volume-veiculos' => 'Mapa de volume de veículos',
            'transito/mapa-infracoes' => 'Mapa de infrações',
            'transito/mapa-acidentes' => 'Mapa de acidentes',
            'transito/mapa-obras' => 'Mapa de obras',
        ] as $path => $title) {
            $this->get('/'.$path)->assertOk()->assertSee($title);
        }
    }

    public function test_infraction_surface_aggregates_only_selected_district_and_period(): void
    {
        $district = Distrito::create(['nome' => 'Centro', 'cidade' => 'Cáceres', 'geojson' => ['type' => 'Polygon', 'coordinates' => [[[0,0],[1,0],[1,1],[0,0]]]]]);
        DB::table('infracoes_transito')->insert([
            ['distrito_id' => $district->id, 'latitude' => -16.07, 'longitude' => -57.68, 'tipo' => 'Excesso de velocidade', 'ocorrido_em' => now()->subHours(2)],
            ['distrito_id' => $district->id, 'latitude' => -16.0701, 'longitude' => -57.6801, 'tipo' => 'Avanço de sinal', 'ocorrido_em' => now()->subHours(2)],
            ['distrito_id' => null, 'latitude' => -16.09, 'longitude' => -57.69, 'tipo' => 'Outra', 'ocorrido_em' => now()->subHours(2)],
            ['distrito_id' => $district->id, 'latitude' => -16.07, 'longitude' => -57.68, 'tipo' => 'Antiga', 'ocorrido_em' => now()->subDays(2)],
        ]);
        $this->get(route('module.intensity', ['module' => 'transito', 'type' => 'infracoes', 'distrito_id' => $district->id]))
            ->assertOk()->assertViewHas('start', fn ($start) => abs($start->diffInHours(now(), false) - 24) < 0.01)
            ->assertViewHas('map', function ($map) {
                $layer = $map->toArray()['layers'][1];
                $properties = $layer['data']['features'][0]['properties'];
                return $layer['type'] === 'surface' && count($layer['data']['features']) === 1
                    && $properties['value'] === 2 && $properties['streets'] === 2
                    && $properties['name'] === 'Excesso de velocidade, Avanço de sinal';
            });
        $this->get(route('module.intensity', ['module' => 'transito', 'type' => 'infracoes', 'engine' => 'leaflet']))
            ->assertOk()->assertViewHas('map', fn ($map) => $map->toArray()['layers'][1]['type'] === 'point');
    }

    public function test_energy_density_uses_the_original_floor_based_grid(): void
    {
        DB::table('postes')->insert([
            ['latitude' => -16.08, 'longitude' => -57.69],
            ['latitude' => -16.08, 'longitude' => -57.6895],
        ]);
        $this->get(route('module.intensity', ['module' => 'energia', 'type' => 'densidade']))
            ->assertOk()->assertViewHas('map', fn ($map) => count($map->toArray()['layers'][1]['data']['features']) === 2);
    }

    public function test_traffic_hour_mode_uses_one_hour_even_when_an_old_end_value_was_submitted(): void
    {
        $this->get(route('module.intensity', [
            'module' => 'transito', 'type' => 'infracoes', 'periodo' => 'hour',
            'inicio' => '2026-11-12T08:30', 'fim' => '2026-11-11T09:30',
        ]))->assertOk()
            ->assertSee('Hora específica')
            ->assertSee('data-traffic-period', false)
            ->assertViewHas('map', fn ($map) => $map->toArray()['layout'] === 'embedded')
            ->assertViewHas('start', fn ($start) => $start->format('Y-m-d H:i') === '2026-11-12 08:30')
            ->assertViewHas('end', fn ($end) => $end->format('Y-m-d H:i') === '2026-11-12 09:30');
    }

    public function test_density_maps_use_their_own_sources_and_validate_period(): void
    {
        Schema::create('equipamentos_infraestrutura', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('distrito_id')->nullable(); $table->float('latitude'); $table->float('longitude'); });
        Schema::create('cabeamentos', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('distrito_id')->nullable(); $table->string('tipo_cabo'); $table->text('geojson'); });
        DB::table('equipamentos_infraestrutura')->insert(['latitude' => -16.08, 'longitude' => -57.69]);
        DB::table('cabeamentos')->insert(['tipo_cabo' => 'fibra_optica', 'geojson' => json_encode(['type' => 'LineString', 'coordinates' => [[-57.69,-16.08],[-57.689,-16.08]]])]);
        DB::table('postes')->insert(['latitude' => -16.07, 'longitude' => -57.68]);
        DB::table('antenas')->insert(['latitude' => -16.08, 'longitude' => -57.69]);
        $this->get(route('module.intensity', ['module' => 'energia', 'type' => 'densidade']))
            ->assertOk()->assertViewHas('map', fn ($map) => $map->toArray()['layers'][1]['data']['features'][0]['geometry']['coordinates'] === [-57.68, -16.07]);
        $this->get(route('module.intensity', ['module' => 'internet', 'type' => 'densidade-rede']))
            ->assertOk()->assertViewHas('map', fn ($map) => array_sum(array_column(array_column($map->toArray()['layers'][1]['data']['features'], 'properties'), 'value')) > 2);
        $this->getJson('/transito/mapa-infracoes?inicio=2026-01-01&fim=2026-03-01')->assertUnprocessable();
    }

    public function test_traffic_volume_accidents_and_roadworks_use_readings_and_road_geometry(): void
    {
        Schema::create('via_transitos', function (Blueprint $table) {
            $table->id(); $table->string('nome'); $table->unsignedBigInteger('distrito_id')->nullable(); $table->text('geojson');
        });
        Schema::create('registro_transitos', function (Blueprint $table) {
            $table->unsignedBigInteger('via_transito_id'); $table->timestamp('timestamp');
            $table->integer('veiculos_total')->nullable(); $table->integer('acidentes_ativos')->nullable(); $table->integer('obras_ativas')->nullable();
        });
        $district = Distrito::create(['nome' => 'Centro', 'cidade' => 'Cáceres', 'geojson' => ['type' => 'Polygon', 'coordinates' => [[[0,0],[1,0],[1,1],[0,0]]]]]);
        $line = json_encode(['type' => 'LineString', 'coordinates' => [[-57.69, -16.07], [-57.688, -16.07]]]);
        DB::table('via_transitos')->insert([
            ['id' => 1, 'nome' => 'Rua Um', 'distrito_id' => $district->id, 'geojson' => $line],
            ['id' => 2, 'nome' => 'Rua Dois', 'distrito_id' => null, 'geojson' => $line],
        ]);
        DB::table('registro_transitos')->insert([
            ['via_transito_id' => 1, 'timestamp' => now()->subHours(2), 'veiculos_total' => 100, 'acidentes_ativos' => 2, 'obras_ativas' => 3],
            ['via_transito_id' => 1, 'timestamp' => now()->subHours(1), 'veiculos_total' => 300, 'acidentes_ativos' => 1, 'obras_ativas' => 1],
            ['via_transito_id' => 2, 'timestamp' => now()->subHours(1), 'veiculos_total' => 900, 'acidentes_ativos' => 9, 'obras_ativas' => 9],
        ]);
        foreach (['volume' => 200, 'acidentes' => 3, 'obras' => 4] as $type => $expected) {
            $this->get(route('module.intensity', ['module' => 'transito', 'type' => $type, 'distrito_id' => $district->id]))
                ->assertOk()->assertViewHas('map', function ($map) use ($expected) {
                    $features = $map->toArray()['layers'][1]['data']['features'];
                    return count($features) === 2 && abs($features[0]['properties']['value'] - $expected) < 0.01
                        && $features[0]['properties']['name'] === 'Rua Um'
                        && $features[0]['properties']['streets'] === 1;
                });
        }
        $this->get(route('module.intensity', ['module' => 'transito', 'type' => 'volume', 'engine' => 'leaflet']))
            ->assertOk()->assertViewHas('map', fn ($map) => $map->toArray()['layers'][1]['type'] === 'point');
    }

    public function test_traffic_maps_preserve_roads_with_zero_metric_readings(): void
    {
        Schema::create('via_transitos', function (Blueprint $table) { $table->id(); $table->string('nome'); $table->unsignedBigInteger('distrito_id')->nullable(); $table->text('geojson'); });
        Schema::create('registro_transitos', function (Blueprint $table) { $table->unsignedBigInteger('via_transito_id'); $table->timestamp('timestamp'); $table->integer('veiculos_total')->nullable(); $table->integer('acidentes_ativos')->nullable(); $table->integer('obras_ativas')->nullable(); });
        DB::table('via_transitos')->insert(['id' => 1, 'nome' => 'Rua sem ocorrências', 'geojson' => json_encode(['type' => 'LineString', 'coordinates' => [[-57.69, -16.07], [-57.688, -16.07]]])]);
        DB::table('registro_transitos')->insert(['via_transito_id' => 1, 'timestamp' => now()->subHour(), 'veiculos_total' => 0, 'acidentes_ativos' => 0, 'obras_ativas' => 0]);
        foreach (['volume', 'acidentes', 'obras'] as $type) {
            $this->get(route('module.intensity', ['module' => 'transito', 'type' => $type]))
                ->assertOk()->assertViewHas('map', fn ($map) => count($map->toArray()['layers'][1]['data']['features']) === 2
                    && $map->toArray()['layers'][1]['data']['features'][0]['properties']['value'] === 0.0);
        }
    }

    public function test_energy_consumption_and_internet_demand_follow_district_population(): void
    {
        $district = Distrito::create(['nome' => 'Centro', 'cidade' => 'Cáceres', 'populacao' => 1000,
            'geojson' => ['type' => 'Polygon', 'coordinates' => [[[-57.68,-16.08],[-57.67,-16.08],[-57.67,-16.07],[-57.68,-16.07],[-57.68,-16.08]]]]]);
        Schema::create('equipamentos_infraestrutura', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('distrito_id')->nullable(); $table->float('latitude'); $table->float('longitude'); });
        DB::table('equipamentos_infraestrutura')->insert(['distrito_id' => $district->id, 'latitude' => -16.075, 'longitude' => -57.675]);
        $expectedSamples = count((new PolygonSampler)->sample($district->geojson, (float) $district->longitude, (float) $district->latitude));
        foreach ([['energia', 'consumo', 180], ['energia', 'populacao', 1000], ['internet', 'demanda', 780]] as [$module, $type, $expected]) {
            $this->get(route('module.intensity', ['module' => $module, 'type' => $type, 'distrito_id' => $district->id]))
                ->assertOk()->assertViewHas('map', function ($map) use ($expected, $expectedSamples) {
                    $features = $map->toArray()['layers'][1]['data']['features'];
                    return count($features) === $expectedSamples && abs($features[0]['properties']['value'] - $expected) < 0.01;
                });
        }
    }
}
