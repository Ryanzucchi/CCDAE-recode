<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Distrito;
use Tests\TestCase;

class AnalyticMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_routes_render_without_optional_sources(): void
    {
        foreach (['energia', 'internet', 'transito'] as $module) {
            $this->get('/'.$module)->assertOk()->assertSee('Visão geral')->assertSee('Indisponível');
        }
    }

    public function test_population_scale_distinguishes_actual_district_values(): void
    {
        $this->get('/mapas/distritos?engine=leaflet')->assertOk()->assertViewHas('map', fn ($map) => $map->toArray()['layers'][0]['scale']['breaks'] === [500, 1000, 1500, 2000, 2500, 3000]);
    }

    public function test_pages_handle_unavailable_sources_without_fake_readings(): void
    {
        foreach (['clima', 'infraestrutura', 'transito'] as $page) {
            $this->get('/mapas/'.$page)->assertOk()->assertSee('disponível');
        }
    }

    public function test_climate_uses_only_selected_district_and_timestamp(): void
    {
        Schema::create('temperatura_registrada', function (Blueprint $table) {
            $table->unsignedBigInteger('distrito_id');
            $table->timestamp('timestamp');
            $table->float('temperatura')->nullable();
            $table->string('origem_dado');
            $table->string('fonte_dado');
        });
        $district = Distrito::create(['nome' => 'Centro', 'cidade' => 'Cáceres', 'geojson' => ['type' => 'Polygon', 'coordinates' => [[[0,0],[1,0],[1,1],[0,0]]]]]);
        $stamp = now()->subHour()->format('Y-m-d H:i:s');
        DB::table('temperatura_registrada')->insert(['distrito_id' => $district->id, 'timestamp' => $stamp, 'temperatura' => 31, 'origem_dado' => 'observado', 'fonte_dado' => 'Estação']);
        $this->get('/mapas/clima?'.http_build_query(['distrito_id' => $district->id, 'at' => $stamp]))
            ->assertOk()->assertViewHas('map', function ($map) {
                $features = $map->toArray()['layers'][1]['data']['features'];
                return count($features) === 1 && $features[0]['properties']['valor'] === 31.0;
            });
        $this->getJson('/mapas/clima?metric=invalid')->assertUnprocessable();
        $this->getJson('/mapas/clima?at=2000-01-01%2000:00:00')->assertUnprocessable();
    }

    public function test_traffic_history_frame_marks_roads_without_readings(): void
    {
        Schema::create('via_transitos', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('distrito_id')->nullable(); $table->string('nome');
            $table->text('geojson'); $table->float('velocidade_media')->nullable();
            $table->string('nivel_congestionamento')->nullable(); $table->string('direcao_fluxo')->nullable(); $table->string('impacto_manutencao')->nullable();
        });
        Schema::create('registro_transitos', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('via_transito_id'); $table->timestamp('timestamp');
            $table->float('velocidade_media')->nullable(); $table->integer('indice_congestionamento')->nullable();
            $table->integer('veiculos_total')->nullable(); $table->integer('acidentes_ativos')->nullable();
            $table->integer('obras_ativas')->nullable(); $table->boolean('alagamento_ativo')->nullable();
            $table->string('origem_dado')->nullable(); $table->string('fonte_dado')->nullable();
            $table->float('confianca_dado')->nullable();
        });
        $geometry = json_encode(['type' => 'LineString', 'coordinates' => [[-57.68, -16.07], [-57.67, -16.06]]]);
        DB::table('via_transitos')->insert([
            ['id' => 1, 'nome' => 'Via com leitura', 'geojson' => $geometry, 'velocidade_media' => 40],
            ['id' => 2, 'nome' => 'Via sem leitura', 'geojson' => $geometry, 'velocidade_media' => 30],
        ]);
        $hour = now()->subHour()->startOfHour();
        $stamp = $hour->format('Y-m-d H:i:s');
        DB::table('registro_transitos')->insert([
            ['via_transito_id' => 1, 'timestamp' => $hour->copy()->addMinutes(5), 'velocidade_media' => 24, 'indice_congestionamento' => 45,
                'veiculos_total' => null, 'acidentes_ativos' => null, 'obras_ativas' => null, 'alagamento_ativo' => null,
                'origem_dado' => null, 'fonte_dado' => null, 'confianca_dado' => null],
            ['via_transito_id' => 1, 'timestamp' => $hour->copy()->addMinutes(15), 'velocidade_media' => 8, 'indice_congestionamento' => 85,
                'veiculos_total' => 135, 'acidentes_ativos' => 1, 'obras_ativas' => 2, 'alagamento_ativo' => true,
                'origem_dado' => 'real', 'fonte_dado' => 'TomTom', 'confianca_dado' => 0.9],
        ]);
        $this->getJson('/mapas/transito?'.http_build_query(['frame' => 1, 'at' => $stamp]))
            ->assertOk()->assertJsonCount(1, 'vias')->assertJsonCount(1, 'viasSemLeitura')
            ->assertJsonPath('vias.0.properties.velocidade_media', 8)
            ->assertJsonPath('vias.0.properties.nivel_congestionamento', 'parado')
            ->assertJsonPath('vias.0.properties.veiculos_total', 135)
            ->assertJsonPath('vias.0.properties.fonte_dado', 'TomTom')
            ->assertJsonPath('vias.0.properties.popup_nome', 'Via: Via com leitura')
            ->assertJsonPath('vias.0.properties.popup_congestionamento', 'PARADO')
            ->assertJsonPath('vias.0.properties.popup_velocidade', '8 km/h')
            ->assertJsonPath('vias.0.properties.popup_direcao', 'Ambos os sentidos')
            ->assertJsonPath('vias.0.properties.popup_volume', '135 veículos')
            ->assertJsonPath('vias.0.properties.popup_ocorrencias', '1 acidente(s), 2 obra(s), alagamento')
            ->assertJsonPath('vias.0.properties.popup_origem', 'Real — TomTom')
            ->assertJsonPath('viasSemLeitura.0.properties.popup_velocidade', 'Não informada')
            ->assertJsonPath('viasSemLeitura.0.properties.nome', 'Via sem leitura');
    }
}
