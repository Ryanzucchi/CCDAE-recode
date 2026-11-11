<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ModuleOverviewMapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['postes', 'antenas', 'equipamentos_infraestrutura', 'centrais_distribuicao', 'cabeamentos'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id(); $table->unsignedBigInteger('distrito_id')->nullable();
                $table->string('codigo_patrimonio')->nullable(); $table->string('nome')->nullable();
                $table->float('latitude')->nullable(); $table->float('longitude')->nullable();
                $table->text('geojson')->nullable(); $table->string('tipo_cabo')->nullable();
            });
        }
    }

    public function test_global_infrastructure_map_uses_tiles_or_viewport_instead_of_inline_limit(): void
    {
        Schema::create('via_transitos', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('distrito_id')->nullable(); $table->string('nome');
            $table->text('geojson'); $table->string('nivel_congestionamento')->nullable();
            $table->float('velocidade_media')->nullable();
        });
        DB::table('postes')->insert([
            ['id' => 1, 'codigo_patrimonio' => 'P-CENTRO', 'latitude' => -16.07, 'longitude' => -57.68],
            ['id' => 2, 'codigo_patrimonio' => 'P-FORA', 'latitude' => -16.25, 'longitude' => -57.95],
        ]);
        DB::table('centrais_distribuicao')->insert(['id' => 3, 'nome' => 'Central Centro', 'latitude' => -16.07, 'longitude' => -57.68]);
        DB::table('centrais_distribuicao')->insert(['id' => 4, 'nome' => 'Central Área', 'latitude' => -16.07,
            'longitude' => -57.68, 'geojson' => json_encode(['type' => 'Polygon', 'coordinates' => [[[-57.69,-16.08],[-57.67,-16.08],[-57.67,-16.06],[-57.69,-16.08]]]])]);
        DB::table('via_transitos')->insert(['id' => 5, 'nome' => 'Via Centro', 'nivel_congestionamento' => 'moderado',
            'velocidade_media' => 30, 'geojson' => json_encode(['type' => 'LineString', 'coordinates' => [[-57.69,-16.08],[-57.67,-16.06]]])]);
        $this->get(route('original-admin.map-infrastructure'))->assertOk()->assertViewHas('map', function ($map) {
            $layers = collect($map->toArray()['layers']);
            $layer = $layers->firstWhere('id', 'postes');
            return $layer['vector']['sourceLayer'] === 'postes' && $layer['data']['features'] === []
                && $layer['minZoom'] === 15
                && $layers->firstWhere('id', 'centrais-areas')['vector']['filter'] === ['==', ['geometry-type'], 'Polygon']
                && $layers->firstWhere('id', 'vias-infraestrutura')['vector']['sourceLayer'] === 'vias';
        });
        $this->get(route('original-admin.map-infrastructure', ['engine' => 'leaflet']))->assertOk()->assertViewHas('map', function ($map) {
            $layer = collect($map->toArray()['layers'])->firstWhere('id', 'postes');
            return $layer['data']['features'] === [] && str_contains($layer['viewport'], '/mapas/infraestrutura-janela/global/0')
                && ! str_contains($layer['viewport'], 'distrito_id=0');
        });
        $this->get(route('maps.infrastructure-viewport', [
            'module' => 'global', 'layer' => 0, 'west' => -57.70, 'south' => -16.09, 'east' => -57.66, 'north' => -16.05,
        ]))->assertOk()->assertJsonCount(1, 'features')->assertJsonPath('features.0.properties.codigo_patrimonio', 'P-CENTRO');
        $this->get(route('maps.infrastructure-viewport', [
            'module' => 'global', 'layer' => 2, 'west' => -57.70, 'south' => -16.09, 'east' => -57.66, 'north' => -16.05,
        ]))->assertOk()->assertJsonCount(1, 'features')->assertJsonPath('features.0.properties.nome', 'Central Centro');
        $this->get(route('maps.infrastructure-viewport', [
            'module' => 'global', 'layer' => 5, 'west' => -57.70, 'south' => -16.09, 'east' => -57.66, 'north' => -16.05,
        ]))->assertOk()->assertJsonCount(1, 'features')->assertJsonPath('features.0.properties.nome', 'Central Área');
        $this->get(route('maps.infrastructure-viewport', [
            'module' => 'global', 'layer' => 6, 'west' => -57.70, 'south' => -16.09, 'east' => -57.66, 'north' => -16.05,
        ]))->assertOk()->assertJsonCount(1, 'features')->assertJsonPath('features.0.properties.nome', 'Via Centro')
            ->assertJsonPath('features.0.properties.velocidade_media', 30);
    }

    public function test_energy_dashboard_shows_electric_layers_and_filters_by_district(): void
    {
        $district = \App\Models\Distrito::create(['nome' => 'Centro', 'cidade' => 'Cáceres', 'geojson' => ['type' => 'Polygon', 'coordinates' => [[[0,0],[1,0],[1,1],[0,0]]]]]);
        DB::table('postes')->insert([['id' => 1, 'distrito_id' => $district->id, 'codigo_patrimonio' => 'P-1', 'latitude' => -16.07, 'longitude' => -57.68], ['id' => 2, 'distrito_id' => null, 'codigo_patrimonio' => 'P-2', 'latitude' => -16.08, 'longitude' => -57.69]]);
        DB::table('cabeamentos')->insert([['id' => 1, 'distrito_id' => $district->id, 'nome' => 'Elétrico', 'tipo_cabo' => 'eletrico_baixa_tensao', 'geojson' => '{"type":"LineString","coordinates":[[-57.68,-16.07],[-57.67,-16.07]]}'], ['id' => 2, 'distrito_id' => $district->id, 'nome' => 'Fibra', 'tipo_cabo' => 'fibra_optica', 'geojson' => '{"type":"LineString","coordinates":[[-57.68,-16.07],[-57.67,-16.07]]}']]);
        $this->get(route('energia', ['distrito_id' => $district->id]))->assertOk()->assertViewHas('map', function ($map) {
            $layers = collect($map->toArray()['layers']);
            return $layers->firstWhere('id', 'infra-0')['vector']['sourceLayer'] === 'energia_postes'
                && $layers->firstWhere('id', 'infra-0')['vector']['filter'] === ['==', ['get', 'distrito_id'], 1]
                && $layers->firstWhere('id', 'infra-2')['vector']['sourceLayer'] === 'energia_centrais'
                && $layers->firstWhere('id', 'infra-3')['vector']['sourceLayer'] === 'energia_cabeamentos'
                && $layers->firstWhere('id', 'infra-energia_postes_3d')['type'] === 'building'
                && $layers->firstWhere('id', 'infra-energia_postes_3d')['minZoom'] === 15
                && $layers->firstWhere('id', 'infra-energia_equipamentos_3d')['height'] === 'render_height';
        });
        $this->get(route('energia', ['distrito_id' => $district->id, 'engine' => 'leaflet']))->assertOk()->assertViewHas('map', function ($map) {
            $layers = collect($map->toArray()['layers']);
            return $layers->firstWhere('id', 'infra-0')['data']['features'] === []
                && str_contains($layers->firstWhere('id', 'infra-0')['viewport'], '/mapas/infraestrutura-janela/energia/0')
                && str_contains($layers->firstWhere('id', 'infra-3')['viewport'], '/mapas/infraestrutura-janela/energia/3');
        });
        $this->get(route('internet', ['distrito_id' => $district->id, 'engine' => 'leaflet']))->assertOk()->assertViewHas('map', function ($map) {
            $payload = $map->toArray();
            return $payload['plugin']['id'] === 'leaflet' && str_contains(collect($payload['layers'])->firstWhere('id', 'infra-2')['viewport'], '/mapas/infraestrutura-janela/internet/2');
        });
        $bbox = ['west' => -57.70, 'south' => -16.09, 'east' => -57.66, 'north' => -16.05, 'distrito_id' => $district->id];
        $this->get(route('maps.infrastructure-viewport', ['module' => 'energia', 'layer' => 0] + $bbox))
            ->assertOk()->assertJsonCount(1, 'features')->assertJsonPath('features.0.properties.codigo_patrimonio', 'P-1');
        $this->get(route('maps.infrastructure-viewport', ['module' => 'energia', 'layer' => 3] + $bbox))
            ->assertOk()->assertJsonCount(1, 'features')->assertJsonPath('features.0.properties.nome', 'Elétrico');
        $this->get(route('maps.infrastructure-viewport', ['module' => 'energia', 'layer' => 0] + ['west' => -58, 'south' => -17, 'east' => -57.9, 'north' => -16.9]))
            ->assertOk()->assertJsonCount(0, 'features');
        $this->get(route('maps.infrastructure-viewport', ['module' => 'energia', 'layer' => 8] + $bbox))->assertNotFound();
        $this->get(route('internet', ['distrito_id' => $district->id]))->assertOk()->assertViewHas('map', function ($map) use ($district) {
            $layer = collect($map->toArray()['layers'])->firstWhere('id', 'infra-internet_antenas_3d');
            return $layer['vector']['sourceLayer'] === 'internet_antenas_3d'
                && $layer['vector']['filter'] === ['==', ['get', 'distrito_id'], $district->id]
                && $layer['minZoom'] === 14;
        });
    }
}
