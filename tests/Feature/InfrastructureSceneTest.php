<?php

namespace Tests\Feature;

use App\Models\Distrito;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InfrastructureSceneTest extends TestCase
{
    use RefreshDatabase;

    public function test_scene_includes_cables_connected_to_district_poles(): void
    {
        $district = Distrito::create(['nome' => 'Centro', 'cidade' => 'Cáceres', 'geojson' => ['type' => 'Polygon', 'coordinates' => [[[-57.69, -16.08], [-57.67, -16.08], [-57.67, -16.06], [-57.69, -16.08]]]]]);
        Schema::create('postes', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('distrito_id')->nullable();
            foreach (['latitude', 'longitude', 'codigo_patrimonio', 'material', 'estado_conservacao', 'altura_metros', 'resistencia_kg', 'possui_iluminacao', 'data_instalacao', 'ultima_manutencao'] as $field) $table->text($field)->nullable();
        });
        Schema::create('cabeamentos', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('distrito_id')->nullable();
            foreach (['geojson', 'nome', 'codigo_patrimonio', 'tipo_cabo', 'capacidade', 'revestimento', 'subterraneo', 'extensao_metros', 'estado_conservacao', 'poste_origem_id', 'poste_destino_id', 'central_origem_id', 'central_destino_id'] as $field) $table->text($field)->nullable();
        });
        DB::table('postes')->insert(['id' => 7, 'distrito_id' => $district->id, 'latitude' => -16.07, 'longitude' => -57.68]);
        DB::table('cabeamentos')->insert([
            ['id' => 21, 'poste_origem_id' => 7, 'geojson' => '{"type":"LineString","coordinates":[[-57.68,-16.07],[-57.67,-16.07]]}'],
            ['id' => 22, 'poste_origem_id' => 999, 'geojson' => '{"type":"LineString","coordinates":[[-57.68,-16.07],[-57.67,-16.07]]}'],
        ]);

        $this->getJson('/mapas/infraestrutura-cena?distrito_id='.$district->id)
            ->assertOk()->assertJsonCount(1, 'postes')->assertJsonCount(1, 'cabeamentos')
            ->assertJsonPath('cabeamentos.0.id', 21);
    }

    public function test_building_viewport_returns_only_visible_polygons(): void
    {
        Schema::create('osm_buildings', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('osm_id'); $table->string('osm_type');
            $table->string('name')->nullable(); $table->string('building')->nullable();
            $table->float('height')->nullable(); $table->float('building_levels')->nullable();
            $table->float('render_height'); $table->float('render_min_height');
            $table->string('height_source'); $table->json('geometry');
        });
        DB::table('osm_buildings')->insert([
            ['id' => 1, 'osm_id' => 10, 'osm_type' => 'way', 'name' => 'Perto', 'render_height' => 12, 'render_min_height' => 0, 'height_source' => 'osm', 'geometry' => json_encode(['type' => 'Polygon', 'coordinates' => [[[-57.68,-16.07],[-57.679,-16.07],[-57.679,-16.069],[-57.68,-16.07]]]])],
            ['id' => 2, 'osm_id' => 11, 'osm_type' => 'way', 'name' => 'Longe', 'render_height' => 9, 'render_min_height' => 0, 'height_source' => 'osm', 'geometry' => json_encode(['type' => 'Polygon', 'coordinates' => [[[-58,-17],[-57.999,-17],[-57.999,-16.999],[-58,-17]]]])],
        ]);

        $this->getJson('/mapas/construcoes-janela?west=-57.69&south=-16.08&east=-57.67&north=-16.06')
            ->assertOk()->assertJsonCount(1, 'features')->assertJsonPath('features.0.properties.name', 'Perto');
        $this->getJson('/mapas/construcoes-janela?west=-58&south=-17&east=-57&north=-16')
            ->assertUnprocessable();
    }
}
