<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RoadResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ccdae.master_email' => 'master@example.test']);
        Schema::create('via_transitos', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('distrito_id')->nullable(); $table->string('nome')->nullable();
            $table->text('geojson')->nullable(); $table->unsignedBigInteger('osm_way_id')->nullable();
            foreach (['tipo', 'sentido', 'direcao_fluxo', 'estado_pavimento', 'nivel_congestionamento', 'impacto_manutencao'] as $field) $table->string($field)->nullable();
            foreach (['numero_faixas', 'limite_velocidade', 'velocidade_media', 'volume_veiculos'] as $field) $table->integer($field)->nullable();
            $table->float('inclinacao_pista')->nullable(); $table->timestamp('ultima_atualizacao'); $table->timestamps();
        });
    }

    private function payload(): array
    {
        return ['nome' => 'Avenida Central', 'tipo' => 'primary', 'numero_faixas' => 2,
            'direcao_fluxo' => 'ambos', 'nivel_congestionamento' => 'livre', 'impacto_manutencao' => 'baixo',
            'ultima_atualizacao' => now()->format('Y-m-d H:i:s'),
            'geojson' => json_encode(['type' => 'LineString', 'coordinates' => [[-57.69,-16.08],[-57.68,-16.07]]])];
    }

    public function test_original_road_urls_keep_navigation_and_writes_on_original_paths(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']));
        $this->get(route('original-roads.index'))->assertOk()->assertSee(route('original-roads.create'));
        $this->get(route('original-roads.create'))->assertOk()->assertSee(route('original-roads.store'));
        $this->post(route('original-roads.store'), $this->payload())->assertRedirect();
        $road = DB::table('via_transitos')->first();
        $this->get(route('original-roads.index'))->assertSee(route('original-roads.show', $road->id))
            ->assertSee(route('original-roads.edit', $road->id));
        $this->get(route('original-roads.show', $road->id))->assertOk()->assertSee(route('original-roads.edit', $road->id));
        $this->get(route('original-roads.edit', $road->id))->assertOk()->assertSee(route('original-roads.update', $road->id));
        $this->put(route('original-roads.update', $road->id), array_merge($this->payload(), ['nome' => 'Avenida Atualizada']))
            ->assertRedirect(route('original-roads.edit', $road->id));
        $this->delete(route('original-roads.destroy', $road->id))->assertRedirect(route('original-roads.index'));
        $this->assertDatabaseCount('via_transitos', 0);
    }

    public function test_master_creates_and_edits_road_without_changing_osm_metadata(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']));
        $this->get(route('roads.create'))->assertOk()->assertSee('Geometria da via');
        $this->post(route('roads.store'), $this->payload())->assertRedirect();
        $road = DB::table('via_transitos')->first();
        $this->assertSame('LineString', json_decode($road->geojson, true)['type']);
        DB::table('via_transitos')->where('id', $road->id)->update(['osm_way_id' => 12345]);
        $this->put(route('roads.update', $road->id), array_merge($this->payload(), ['nome' => 'Avenida Nova', 'osm_way_id' => 999]))->assertRedirect();
        $this->assertSame('Avenida Nova', DB::table('via_transitos')->first()->nome);
        $this->assertSame(12345, DB::table('via_transitos')->first()->osm_way_id);
        $this->get(route('roads.index', ['q' => 'Nova']))->assertOk()->assertSee('Avenida Nova');
    }

    public function test_invalid_geometry_and_non_master_writes_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']));
        $this->postJson(route('roads.store'), array_merge($this->payload(), ['geojson' => '{"type":"Point","coordinates":[0,0]}']))->assertUnprocessable();
        $this->actingAs(User::factory()->create(['email' => 'other@example.test']));
        $this->post(route('roads.store'), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('via_transitos', 0);
    }

    public function test_road_listing_sorts_and_details_open_without_editing(): void
    {
        $stamp = now()->format('Y-m-d H:i:s');
        DB::table('via_transitos')->insert([
            ['id' => 1, 'nome' => 'Avenida Alta', 'tipo' => 'primary', 'numero_faixas' => 3, 'velocidade_media' => 60, 'direcao_fluxo' => 'inicio_fim', 'ultima_atualizacao' => $stamp],
            ['id' => 2, 'nome' => 'Rua Baixa', 'tipo' => 'residential', 'numero_faixas' => 1, 'velocidade_media' => 20, 'direcao_fluxo' => 'ambos', 'ultima_atualizacao' => $stamp],
        ]);
        $this->get(route('roads.index', ['sort' => 'velocidade_media', 'direction' => 'desc']))
            ->assertOk()->assertViewHas('items', fn ($items) => $items->getCollection()->pluck('nome')->all() === ['Avenida Alta', 'Rua Baixa'])
            ->assertSee('Visualizar');
        $this->get(route('roads.show', 1))->assertOk()->assertSee('Avenida Alta')->assertSee('Início → fim da geometria');
        $this->get(route('roads.show', 999))->assertNotFound();
        $this->getJson(route('roads.index', ['sort' => 'velocidade_media desc']))->assertUnprocessable();
    }

    public function test_bulk_delete_removes_only_selected_roads_for_master(): void
    {
        $stamp = now()->format('Y-m-d H:i:s');
        DB::table('via_transitos')->insert([
            ['id' => 1, 'nome' => 'Via Um', 'ultima_atualizacao' => $stamp],
            ['id' => 2, 'nome' => 'Via Dois', 'ultima_atualizacao' => $stamp],
        ]);
        $this->get(route('roads.index'))->assertOk()->assertDontSee('Excluir selecionadas');
        $this->actingAs(User::factory()->create(['email' => 'other@example.test']))
            ->post(route('roads.destroy-batch'), ['selected' => [1]])->assertForbidden();
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']))
            ->postJson(route('roads.destroy-batch'), ['selected' => [1, 1]])->assertUnprocessable();
        $this->get(route('roads.index'))->assertOk()->assertSee('Excluir selecionadas');
        $this->post(route('roads.destroy-batch'), ['selected' => [1]])->assertRedirect(route('roads.index'));
        $this->assertDatabaseMissing('via_transitos', ['id' => 1]);
        $this->assertDatabaseHas('via_transitos', ['id' => 2]);
    }

    public function test_edit_page_deletes_one_road_for_master(): void
    {
        $stamp = now()->format('Y-m-d H:i:s');
        DB::table('via_transitos')->insert([
            ['id' => 1, 'nome' => 'Via Um', 'ultima_atualizacao' => $stamp],
            ['id' => 2, 'nome' => 'Via Dois', 'ultima_atualizacao' => $stamp],
        ]);
        $route = route('roads.destroy', 1);
        $this->delete($route)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['email' => 'other@example.test']))->delete($route)->assertForbidden();
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']))
            ->get(route('roads.edit', 1))->assertOk()->assertSee($route);
        $this->delete($route)->assertRedirect(route('roads.index'));
        $this->assertDatabaseMissing('via_transitos', ['id' => 1]);
        $this->assertDatabaseHas('via_transitos', ['id' => 2]);
    }
}
