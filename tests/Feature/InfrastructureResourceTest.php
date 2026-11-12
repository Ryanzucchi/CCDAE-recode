<?php
namespace Tests\Feature;

use App\Models\Distrito;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InfrastructureResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ccdae.master_email' => 'master@example.test']);
        foreach (['postes', 'antenas'] as $name) Schema::create($name, function (Blueprint $table) use ($name) {
            $table->id(); $table->unsignedBigInteger('distrito_id')->nullable();
            $table->string('codigo_patrimonio')->nullable()->unique();
            $table->decimal('latitude')->nullable(); $table->decimal('longitude')->nullable();
            $table->string('estado_conservacao'); $table->date('data_instalacao')->nullable(); $table->date('ultima_manutencao')->nullable();
            $table->text('observacoes')->nullable();
            if ($name === 'postes') {
                $table->string('material'); $table->decimal('altura_metros')->nullable();
                $table->integer('resistencia_kg')->nullable(); $table->boolean('possui_iluminacao');
            } else {
                $table->string('tipo_sinal')->nullable(); $table->decimal('frequencia_mhz')->nullable();
                $table->decimal('alcance_metros')->nullable(); $table->decimal('potencia_dbm')->nullable();
                $table->string('proprietario')->nullable();
            }
            $table->timestamps();
        });
        Schema::create('centrais_distribuicao', function (Blueprint $table) {
            $table->id(); $table->string('nome'); $table->string('codigo_patrimonio')->nullable()->unique();
            $table->unsignedBigInteger('distrito_id')->nullable(); $table->string('tipo')->nullable(); $table->string('capacidade')->nullable();
            $table->decimal('area_m2')->nullable(); $table->decimal('latitude')->nullable(); $table->decimal('longitude')->nullable();
            $table->text('geojson')->nullable(); $table->string('estado_conservacao')->nullable();
            $table->date('data_instalacao')->nullable(); $table->date('ultima_manutencao')->nullable();
            $table->text('observacoes')->nullable(); $table->timestamps();
        });
        Schema::create('cabeamentos', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('distrito_id')->nullable();
            $table->string('nome')->nullable(); $table->string('codigo_patrimonio')->nullable()->unique();
            $table->string('tipo_cabo')->nullable(); $table->string('capacidade')->nullable();
            $table->string('revestimento')->nullable(); $table->boolean('subterraneo');
            $table->decimal('extensao_metros')->nullable(); $table->text('geojson')->nullable();
            foreach (['poste_origem_id','poste_destino_id','central_origem_id','central_destino_id'] as $field) $table->unsignedBigInteger($field)->nullable();
            $table->string('estado_conservacao'); $table->date('data_instalacao')->nullable(); $table->date('ultima_manutencao')->nullable();
            $table->text('observacoes')->nullable(); $table->timestamps();
        });
        Schema::create('equipamentos_infraestrutura', function (Blueprint $table) {
            $table->id(); $table->string('nome'); $table->string('codigo_patrimonio')->nullable()->unique();
            $table->string('tipo')->nullable(); $table->string('modelo')->nullable(); $table->string('numero_serie')->nullable();
            $table->unsignedBigInteger('distrito_id')->nullable(); $table->unsignedBigInteger('poste_id')->nullable();
            $table->unsignedBigInteger('central_id')->nullable(); $table->decimal('latitude')->nullable(); $table->decimal('longitude')->nullable();
            $table->string('estado_conservacao'); $table->date('data_instalacao')->nullable(); $table->date('ultima_manutencao')->nullable();
            $table->text('observacoes')->nullable(); $table->timestamps();
        });
    }

    public function test_master_can_create_search_and_edit_post_and_antenna(): void
    {
        $district = Distrito::create(['nome' => 'Centro', 'cidade' => 'Cáceres', 'geojson' => ['type' => 'Polygon', 'coordinates' => [[[0,0],[1,0],[1,1],[0,0]]]]]);
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']));
        $this->get(route('infrastructure.resource.create', 'postes'))->assertOk()->assertSee('Possui iluminação');
        $this->post(route('infrastructure.resource.store', 'postes'), [
            'distrito_id' => $district->id, 'codigo_patrimonio' => 'P-123', 'material' => 'concreto',
            'estado_conservacao' => 'bom', 'possui_iluminacao' => 1, 'latitude' => -16.07, 'longitude' => -57.68,
        ])->assertRedirect();
        $post = DB::table('postes')->first();
        $this->assertSame('P-123', $post->codigo_patrimonio);
        $this->get(route('infrastructure.resource.index', ['kind' => 'postes', 'q' => 'P-123']))->assertOk()->assertSee('P-123');
        $this->put(route('infrastructure.resource.update', ['kind' => 'postes', 'id' => $post->id]), [
            'codigo_patrimonio' => 'P-123', 'material' => 'madeira', 'estado_conservacao' => 'regular',
            'possui_iluminacao' => 0,
        ])->assertRedirect();
        $this->assertSame('madeira', DB::table('postes')->first()->material);
        $this->post(route('infrastructure.resource.store', 'antenas'), [
            'codigo_patrimonio' => 'A-123', 'tipo_sinal' => '5G', 'estado_conservacao' => 'bom',
            'frequencia_mhz' => 3500,
        ])->assertRedirect();
        $this->assertSame('5G', DB::table('antenas')->first()->tipo_sinal);
    }

    public function test_infrastructure_form_sections_follow_original_filament_order_and_columns(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']));
        $this->get(route('infrastructure.resource.create', 'postes'))->assertOk()
            ->assertSee('data-geoveil-select-filter="gv-postes-distrito_id"', false)
            ->assertSeeInOrder(['Dados Principais', 'data-columns="2"', 'Desgaste &amp; Vida Útil', 'data-columns="3"', 'Localização Espacial (Nó do Grafo)'], false);
        $this->get(route('infrastructure.resource.create', 'cabeamentos'))->assertOk()
            ->assertSeeInOrder(['Dados Principais (Aresta do Grafo)', 'Desgaste &amp; Vida Útil', 'Roteamento do Grafo (Conexões Físicas)', 'Traçado Espacial (Edge)'], false);
    }

    public function test_original_infrastructure_slug_keeps_list_create_edit_and_save_on_original_paths(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']));
        foreach (array_keys(\App\Services\InfrastructureResourceCatalog::ORIGINAL_SLUGS) as $kind) {
            $this->get(route('original-resource.'.$kind.'.index'))->assertOk()
                ->assertSee(route('original-resource.'.$kind.'.create'));
            $this->get(route('original-resource.'.$kind.'.create'))->assertOk();
        }
        $index = route('original-resource.postes.index');
        $create = route('original-resource.postes.create');
        $this->get($index)->assertOk()->assertSee($create)->assertSee(route('original-admin.map-infrastructure'));
        $this->get($create)->assertOk()->assertSee(route('original-resource.postes.store'));
        $this->post(route('original-resource.postes.store'), [
            'codigo_patrimonio' => 'P-ORIGINAL', 'material' => 'concreto',
            'estado_conservacao' => 'bom', 'possui_iluminacao' => 1,
        ])->assertRedirect(route('original-resource.postes.edit', 1));
        $this->get($index)->assertOk()->assertSee(route('original-resource.postes.edit', 1));
        $this->get(route('original-resource.postes.edit', 1))->assertOk()
            ->assertSee(route('original-resource.postes.update', 1))
            ->assertSee(route('original-resource.postes.destroy', 1));
        $this->put(route('original-resource.postes.update', 1), [
            'codigo_patrimonio' => 'P-ORIGINAL', 'material' => 'madeira',
            'estado_conservacao' => 'regular', 'possui_iluminacao' => 0,
        ])->assertRedirect(route('original-resource.postes.edit', 1));
        $this->assertSame('madeira', DB::table('postes')->first()->material);
    }

    public function test_infrastructure_lists_expose_original_columns_search_and_sort_rules(): void
    {
        DB::table('postes')->insert([
            ['codigo_patrimonio' => 'P-M', 'material' => 'madeira', 'altura_metros' => 8, 'estado_conservacao' => 'regular', 'possui_iluminacao' => 1],
            ['codigo_patrimonio' => 'P-F', 'material' => 'ferro', 'altura_metros' => 12, 'estado_conservacao' => 'bom', 'possui_iluminacao' => 0],
        ]);
        $this->get(route('infrastructure.resource.index', ['kind' => 'postes', 'q' => 'madeira']))
            ->assertOk()->assertSee('P-M')->assertDontSee('P-F')
            ->assertSee('Latitude')->assertSee('Longitude')->assertSee('Resistência (kg)')->assertSee('Última manutenção')
            ->assertSee('data-column-toggle="created_at"', false)->assertSee('data-column-toggle="updated_at"', false)
            ->assertSee('data-optional-column="created_at" hidden', false);
        $this->get(route('infrastructure.resource.index', ['kind' => 'postes', 'sort' => 'altura_metros', 'direction' => 'desc']))
            ->assertOk()->assertViewHas('items', fn ($items) => $items->items()[0]->codigo_patrimonio === 'P-F');
        $this->getJson(route('infrastructure.resource.index', ['kind' => 'postes', 'sort' => 'material']))->assertUnprocessable();
        foreach (['antenas' => 'Potência (dBm)', 'equipamentos_infraestrutura' => 'Número de série',
            'centrais_distribuicao' => 'Área (m²)', 'cabeamentos' => 'Central de destino'] as $kind => $column) {
            $this->get(route('infrastructure.resource.index', $kind))->assertOk()->assertSee($column);
        }
        $resource = app(\App\Services\InfrastructureResourceCatalog::class)->get('postes');
        $this->assertSame(['created_at' => 'Criado em', 'updated_at' => 'Atualizado em'], $resource->getOptionalColumns());
        $this->assertArrayNotHasKey('created_at', $resource->getColumns());
    }

    public function test_infrastructure_bulk_delete_requires_master_and_only_removes_selected_rows(): void
    {
        $first = DB::table('postes')->insertGetId(['codigo_patrimonio' => 'P-1', 'material' => 'concreto', 'estado_conservacao' => 'bom', 'possui_iluminacao' => 0]);
        $second = DB::table('postes')->insertGetId(['codigo_patrimonio' => 'P-2', 'material' => 'concreto', 'estado_conservacao' => 'bom', 'possui_iluminacao' => 0]);
        $route = route('infrastructure.resource.destroy-batch', 'postes');
        $this->post($route, ['selected' => [$first]])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['email' => 'other@example.test']))
            ->post($route, ['selected' => [$first]])->assertForbidden();
        $this->get(route('infrastructure.resource.index', 'postes'))->assertOk()->assertDontSee('Excluir selecionados');
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']))
            ->postJson($route, ['selected' => [$first, $first]])->assertUnprocessable();
        $this->get(route('infrastructure.resource.index', 'postes'))->assertOk()->assertSee('Excluir selecionados');
        $this->post($route, ['selected' => [$first]])->assertRedirect(route('infrastructure.resource.index', 'postes'));
        $this->assertDatabaseMissing('postes', ['id' => $first]);
        $this->assertDatabaseHas('postes', ['id' => $second]);
    }

    public function test_edit_page_deletes_only_its_record_for_the_master(): void
    {
        $first = DB::table('postes')->insertGetId(['codigo_patrimonio' => 'P-EDIT-1', 'material' => 'concreto', 'estado_conservacao' => 'bom', 'possui_iluminacao' => 0]);
        $second = DB::table('postes')->insertGetId(['codigo_patrimonio' => 'P-EDIT-2', 'material' => 'concreto', 'estado_conservacao' => 'bom', 'possui_iluminacao' => 0]);
        $route = route('infrastructure.resource.destroy', ['postes', $first]);
        $this->delete($route)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['email' => 'other@example.test']))->delete($route)->assertForbidden();
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']))
            ->get(route('infrastructure.resource.edit', ['postes', $first]))->assertOk()->assertSee($route);
        $this->delete($route)->assertRedirect(route('infrastructure.resource.index', 'postes'));
        $this->assertDatabaseMissing('postes', ['id' => $first]);
        $this->assertDatabaseHas('postes', ['id' => $second]);
        $this->delete($route)->assertNotFound();
    }

    public function test_equipment_form_preserves_links_to_posts_and_centrals(): void
    {
        $user = User::factory()->create(['email' => 'master@example.test']);
        $this->actingAs($user);
        $post = DB::table('postes')->insertGetId(['codigo_patrimonio' => 'P-1', 'material' => 'concreto', 'estado_conservacao' => 'bom', 'possui_iluminacao' => 0]);
        $central = DB::table('centrais_distribuicao')->insertGetId(['nome' => 'Central 1']);
        $this->get(route('infrastructure.resource.create', 'equipamentos_infraestrutura'))->assertOk()->assertSee('Alocado no Poste');
        $this->post(route('infrastructure.resource.store', 'equipamentos_infraestrutura'), [
            'nome' => 'Transformador 1', 'codigo_patrimonio' => 'E-1', 'tipo' => 'Transformador',
            'poste_id' => $post, 'central_id' => $central, 'estado_conservacao' => 'bom',
            'latitude' => -16.07, 'longitude' => -57.68,
        ])->assertRedirect();
        $item = DB::table('equipamentos_infraestrutura')->first();
        $this->assertSame($post, $item->poste_id);
        $this->get(route('infrastructure.resource.index', ['kind' => 'equipamentos_infraestrutura', 'q' => 'Transformador']))->assertOk()->assertSee('Transformador 1');
        $this->get(route('infrastructure.resource.edit', ['kind' => 'equipamentos_infraestrutura', 'id' => $item->id]))->assertOk()->assertSee('Central 1');
    }

    public function test_central_accepts_polygon_and_computes_center(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']));
        $this->get(route('infrastructure.resource.create', 'centrais_distribuicao'))->assertOk()->assertSee('Polígono ou ponto da central')->assertSee('Editar vértices')->assertSee('Desenhar retângulo');
        $polygon = ['type' => 'Polygon', 'coordinates' => [[[-57.69,-16.08],[-57.68,-16.08],[-57.68,-16.07],[-57.69,-16.08]]]];
        $this->post(route('infrastructure.resource.store', 'centrais_distribuicao'), [
            'nome' => 'Central Norte', 'tipo' => 'Energia', 'estado_conservacao' => 'bom', 'geojson' => json_encode($polygon),
        ])->assertRedirect();
        $central = DB::table('centrais_distribuicao')->first();
        $this->assertEqualsWithDelta(-57.6833, $central->longitude, 0.001);
        $this->assertSame('Polygon', json_decode($central->geojson, true)['type']);
        $this->postJson(route('infrastructure.resource.store', 'centrais_distribuicao'), [
            'nome' => 'Inválida', 'estado_conservacao' => 'bom', 'geojson' => '{"type":"Polygon","coordinates":[]}',
        ])->assertUnprocessable();
        $legacy = DB::table('centrais_distribuicao')->insertGetId(['nome' => 'POP legado', 'tipo' => 'POP', 'estado_conservacao' => 'bom']);
        $this->get(route('infrastructure.resource.edit', ['kind' => 'centrais_distribuicao', 'id' => $legacy]))->assertOk()->assertSee('value="POP"', false);
        $this->put(route('infrastructure.resource.update', ['kind' => 'centrais_distribuicao', 'id' => $legacy]), [
            'nome' => 'POP corrigido', 'tipo' => 'POP', 'estado_conservacao' => 'bom',
        ])->assertRedirect();
        $this->assertSame('POP', DB::table('centrais_distribuicao')->where('id', $legacy)->value('tipo'));
        $this->assertDatabaseCount('centrais_distribuicao', 2);
    }

    public function test_cable_route_can_be_drawn_or_derived_from_connected_nodes(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']));
        $from = DB::table('postes')->insertGetId(['codigo_patrimonio' => 'P-A', 'material' => 'concreto', 'estado_conservacao' => 'bom', 'possui_iluminacao' => 0, 'latitude' => -16.08, 'longitude' => -57.69]);
        $to = DB::table('postes')->insertGetId(['codigo_patrimonio' => 'P-B', 'material' => 'concreto', 'estado_conservacao' => 'bom', 'possui_iluminacao' => 0, 'latitude' => -16.07, 'longitude' => -57.68]);
        $this->get(route('infrastructure.resource.create', 'cabeamentos'))->assertOk()->assertSee('Desenhar linha');
        $this->post(route('infrastructure.resource.store', 'cabeamentos'), [
            'nome' => 'Fibra A', 'tipo_cabo' => 'fibra_optica', 'poste_origem_id' => $from,
            'poste_destino_id' => $to, 'estado_conservacao' => 'bom', 'subterraneo' => 1,
        ])->assertRedirect();
        $cable = DB::table('cabeamentos')->first();
        $this->assertSame('LineString', json_decode($cable->geojson, true)['type']);
        $this->assertGreaterThan(1000, $cable->extensao_metros);
        $line = ['type' => 'LineString', 'coordinates' => [[-57.69,-16.08],[-57.685,-16.075],[-57.68,-16.07]]];
        $this->put(route('infrastructure.resource.update', ['kind' => 'cabeamentos', 'id' => $cable->id]), [
            'nome' => 'Fibra A', 'tipo_cabo' => 'fibra_optica', 'estado_conservacao' => 'bom',
            'subterraneo' => 0, 'geojson' => json_encode($line),
        ])->assertRedirect();
        $this->assertCount(3, json_decode(DB::table('cabeamentos')->first()->geojson, true)['coordinates']);
        $newDestination = DB::table('postes')->insertGetId(['codigo_patrimonio' => 'P-C', 'material' => 'concreto', 'estado_conservacao' => 'bom', 'possui_iluminacao' => 0, 'latitude' => -16.06, 'longitude' => -57.67]);
        $this->put(route('infrastructure.resource.update', ['kind' => 'cabeamentos', 'id' => $cable->id]), [
            'nome' => 'Fibra A', 'tipo_cabo' => 'fibra_optica', 'estado_conservacao' => 'bom', 'subterraneo' => 0,
            'poste_origem_id' => $from, 'poste_destino_id' => $newDestination, 'geojson' => json_encode($line),
            'geometry_manual' => 0,
        ])->assertRedirect();
        $routed = json_decode(DB::table('cabeamentos')->where('id', $cable->id)->value('geojson'), true);
        $this->assertCount(2, $routed['coordinates']);
        $this->assertEqualsWithDelta(-57.67, $routed['coordinates'][1][0], 0.00001);
        $this->put(route('infrastructure.resource.update', ['kind' => 'cabeamentos', 'id' => $cable->id]), [
            'nome' => 'Fibra A', 'tipo_cabo' => 'fibra_optica', 'estado_conservacao' => 'bom', 'subterraneo' => 0,
            'poste_origem_id' => $from, 'poste_destino_id' => $to, 'geojson' => json_encode($line),
            'geometry_manual' => 1,
        ])->assertRedirect();
        $this->assertCount(3, json_decode(DB::table('cabeamentos')->where('id', $cable->id)->value('geojson'), true)['coordinates']);
        $this->postJson(route('infrastructure.resource.store', 'cabeamentos'), [
            'estado_conservacao' => 'bom', 'subterraneo' => 0,
            'geojson' => json_encode(['type' => 'Polygon', 'coordinates' => [[[-57.69,-16.08],[-57.68,-16.08],[-57.69,-16.08]]]]),
        ])->assertUnprocessable();
    }

    public function test_remote_lookup_finds_post_beyond_first_thousand_and_requires_master(): void
    {
        $rows = [];
        for ($i = 1; $i <= 1100; $i++) $rows[] = [
            'codigo_patrimonio' => 'P-'.str_pad((string)$i, 4, '0', STR_PAD_LEFT),
            'material' => 'concreto', 'estado_conservacao' => 'bom', 'possui_iluminacao' => 0,
        ];
        foreach (array_chunk($rows, 200) as $chunk) DB::table('postes')->insert($chunk);
        $target = DB::table('postes')->where('codigo_patrimonio', 'P-1100')->value('id');
        $this->getJson(route('resource.lookup', ['kind' => 'postes', 'q' => 'P-1100']))->assertUnauthorized();
        $this->actingAs(User::factory()->create(['email' => 'other@example.test']));
        $this->getJson(route('resource.lookup', ['kind' => 'postes', 'q' => 'P-1100']))->assertForbidden();
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']));
        $this->getJson(route('resource.lookup', ['kind' => 'postes', 'q' => 'P-1100']))
            ->assertOk()->assertJsonPath('results.0.id', $target);
        $this->getJson(route('resource.lookup', ['kind' => 'postes', 'id' => $target]))
            ->assertOk()->assertJsonPath('results.0.id', $target)->assertJsonStructure(['results' => [['longitude', 'latitude']]]);
        DB::table('cabeamentos')->insert([
            ['nome' => 'Cabo teste fibra', 'tipo_cabo' => 'fibra_optica', 'subterraneo' => 0, 'estado_conservacao' => 'bom'],
            ['nome' => 'Cabo teste energia', 'tipo_cabo' => 'eletrico_baixa_tensao', 'subterraneo' => 0, 'estado_conservacao' => 'bom'],
        ]);
        $this->getJson(route('resource.lookup', ['kind' => 'cabeamentos', 'domain' => 'internet', 'q' => 'Cabo teste']))
            ->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('results.0.label', 'Cabo teste fibra #1');
        $this->getJson(route('resource.lookup', ['kind' => 'cabeamentos', 'domain' => 'energy', 'q' => 'Cabo teste']))
            ->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('results.0.label', 'Cabo teste energia #2');
    }

    public function test_invalid_values_and_non_master_writes_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']));
        $this->postJson(route('infrastructure.resource.store', 'postes'), [
            'material' => 'plastico', 'estado_conservacao' => 'bom', 'possui_iluminacao' => 0,
        ])->assertUnprocessable();
        $this->actingAs(User::factory()->create(['email' => 'other@example.test']));
        $this->post(route('infrastructure.resource.store', 'postes'), [
            'material' => 'concreto', 'estado_conservacao' => 'bom', 'possui_iluminacao' => 0,
        ])->assertForbidden();
        $this->assertDatabaseCount('postes', 0);
    }
}
