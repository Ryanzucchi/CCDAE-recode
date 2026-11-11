<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClimateResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_seven_climate_tables_share_catalog_and_filter_by_district(): void
    {
        $tables = [
            'temperatura_registrada' => ['temperatura' => 29.3, 'sensacao_termica' => 31.2],
            'vento_registrado' => ['velocidade' => 9.1, 'direcao' => 180],
            'chuva_registrada' => ['precipitacao_mm' => 4.2],
            'pressao_atmosferica' => ['pressao_hpa' => 1012.1],
            'radiacao_solar' => ['radiacao_w_m2' => 430.2],
            'indice_uv' => ['uv' => 7.3],
            'particulas_ar' => ['pm25' => 10.1, 'pm10' => 18.4, 'poeira' => 5.1, 'areia' => 2.1, 'poluentes' => 3.1],
        ];
        DB::table('distritos')->insert([['id' => 1, 'nome' => 'Centro', 'geojson' => '{}'], ['id' => 2, 'nome' => 'Norte', 'geojson' => '{}']]);
        foreach ($tables as $table => $fields) {
            Schema::create($table, function (Blueprint $schema) use ($fields) {
                $schema->unsignedBigInteger('distrito_id'); $schema->timestamp('timestamp');
                foreach ($fields as $field => $value) $schema->float($field)->nullable();
            });
            DB::table($table)->insert(['distrito_id' => 1, 'timestamp' => '2026-11-05 10:00:00'] + $fields);
        }
        foreach (array_keys(\App\Services\ClimateResource::TYPES) as $kind) {
            $this->get(route('climate.resources.index', $kind))->assertOk()->assertSee('Centro');
            $this->get(route('original-climate.'.$kind.'.index'))->assertOk()->assertSee('Centro');
            $this->get(route('climate.resources.index', ['kind' => $kind, 'distrito_id' => 2]))->assertOk()->assertDontSee('2026-11-05 10:00:00');
            $this->get(route('original-climate.'.$kind.'.index', ['distrito_id' => 2]))->assertOk()->assertDontSee('2026-11-05 10:00:00');
        }
    }

    public function test_climate_columns_sort_and_format_like_original_table(): void
    {
        DB::table('distritos')->insert([['id' => 1, 'nome' => 'Centro', 'geojson' => '{}'], ['id' => 2, 'nome' => 'Norte', 'geojson' => '{}']]);
        Schema::create('temperatura_registrada', function (Blueprint $schema) {
            $schema->unsignedBigInteger('distrito_id'); $schema->timestamp('timestamp'); $schema->float('temperatura')->nullable();
        });
        DB::table('temperatura_registrada')->insert([
            ['distrito_id' => 1, 'timestamp' => '2026-11-05 09:00:00', 'temperatura' => 33],
            ['distrito_id' => 2, 'timestamp' => '2026-11-05 10:00:00', 'temperatura' => 12],
        ]);
        $response = $this->get('/admin/clima/temperatura?sort=temperatura&direction=desc');
        $response->assertOk()->assertViewHas('items', fn ($items) => $items->getCollection()->pluck('distrito_nome')->all() === ['Centro', 'Norte'])
            ->assertSee('05/11/2026 09:00')->assertSee('is-hot');
        $this->getJson('/admin/clima/temperatura?sort=timestamp%20desc')->assertUnprocessable();
    }
}
