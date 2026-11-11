<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TrafficRecordResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ccdae.master_email' => 'master@example.test']);
        Schema::create('via_transitos', function (Blueprint $table) {
            $table->id(); $table->string('nome');
        });
        Schema::create('registro_transitos', function (Blueprint $table) {
            $table->unsignedBigInteger('via_transito_id'); $table->timestamp('timestamp');
            $table->integer('veiculos_total')->nullable(); $table->float('velocidade_media')->nullable();
            $table->float('altura_media_veiculos')->nullable(); $table->float('altura_maxima_veiculos')->nullable();
            $table->integer('indice_congestionamento')->nullable(); $table->string('nivel_servico')->nullable();
            $table->primary(['via_transito_id', 'timestamp']);
        });
        DB::table('via_transitos')->insert([['id' => 1, 'nome' => 'Rua Um'], ['id' => 2, 'nome' => 'Rua Dois']]);
    }

    private function payload(array $changes = []): array
    {
        return array_merge(['via_transito_id' => 1, 'timestamp' => '2026-11-05T10:30:00', 'veiculos_total' => 42], $changes);
    }

    public function test_original_record_paths_keep_composite_key_through_crud(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']));
        $this->get(route('original-traffic.records.index'))->assertOk()->assertSee(route('original-traffic.records.create'));
        $this->get(route('original-traffic.records.create'))->assertOk()->assertSee(route('original-traffic.records.store'));
        $key = '1-20261105T103000';
        $this->post(route('original-traffic.records.store'), $this->payload())
            ->assertRedirect(route('original-traffic.records.edit', $key));
        $this->get(route('original-traffic.records.index'))->assertSee(route('original-traffic.records.show', $key))
            ->assertSee(route('original-traffic.records.edit', $key));
        $this->get(route('original-traffic.records.show', $key))->assertOk()->assertSee('Rua Um');
        $this->get('/admin/registro-transitos/1-20260230T103000')->assertNotFound();
        $this->get(route('original-traffic.records.edit', $key))->assertOk()
            ->assertSee(route('original-traffic.records.update', $key));
        $this->put(route('original-traffic.records.update', $key), $this->payload(['veiculos_total' => 45]))
            ->assertRedirect(route('original-traffic.records.edit', $key));
        $this->assertDatabaseHas('registro_transitos', ['veiculos_total' => 45]);
        $this->delete(route('original-traffic.records.destroy', $key))
            ->assertRedirect(route('original-traffic.records.index'));
        $this->assertDatabaseCount('registro_transitos', 0);
    }

    public function test_master_can_create_and_change_composite_key_without_touching_other_reading(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']));
        $this->get(route('traffic.records.create'))->assertOk()->assertSee('Via de trânsito');
        $this->post(route('traffic.records.store'), $this->payload())->assertRedirect();
        $this->post(route('traffic.records.store'), $this->payload(['via_transito_id' => 2, 'veiculos_total' => 8]))->assertRedirect();
        $this->get(route('traffic.records.index', ['q' => 'Rua Um']))->assertOk()->assertSee('Rua Um')->assertDontSee('Rua Dois');
        $key = ['via_id' => 1, 'at' => '2026-11-05 10:30:00'];
        $this->get(route('traffic.records.edit', $key))->assertOk()->assertSee('42');
        $this->put(route('traffic.records.update', $key), $this->payload(['timestamp' => '2026-11-05T11:00:00', 'veiculos_total' => 55]))->assertRedirect();
        $this->assertDatabaseHas('registro_transitos', ['via_transito_id' => 1, 'timestamp' => '2026-11-05 11:00:00', 'veiculos_total' => 55]);
        $this->assertDatabaseHas('registro_transitos', ['via_transito_id' => 2, 'timestamp' => '2026-11-05 10:30:00', 'veiculos_total' => 8]);
        $this->assertDatabaseMissing('registro_transitos', ['via_transito_id' => 1, 'timestamp' => '2026-11-05 10:30:00']);
    }

    public function test_duplicate_and_non_master_write_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']));
        $this->post(route('traffic.records.store'), $this->payload())->assertRedirect();
        $this->postJson(route('traffic.records.store'), $this->payload())->assertUnprocessable();
        $this->actingAs(User::factory()->create(['email' => 'other@example.test']));
        $this->post(route('traffic.records.store'), $this->payload(['via_transito_id' => 2]))->assertForbidden();
        $this->assertDatabaseCount('registro_transitos', 1);
    }

    public function test_readings_can_be_sorted_and_viewed_by_composite_key(): void
    {
        DB::table('registro_transitos')->insert([
            ['via_transito_id' => 1, 'timestamp' => '2026-11-05 10:00:00', 'veiculos_total' => 42],
            ['via_transito_id' => 2, 'timestamp' => '2026-11-05 11:00:00', 'veiculos_total' => 9],
        ]);
        $this->get(route('traffic.records.index', ['sort' => 'veiculos_total', 'direction' => 'desc']))
            ->assertOk()->assertViewHas('items', fn ($items) => $items->getCollection()->pluck('via_nome')->all() === ['Rua Um', 'Rua Dois'])
            ->assertSee('Visualizar');
        $this->get(route('traffic.records.show', ['via_id' => 1, 'at' => '2026-11-05 10:00:00']))
            ->assertOk()->assertSee('Rua Um')->assertSee('42')->assertSee('05/11/2026 10:00:00');
        $this->get(route('traffic.records.show', ['via_id' => 1, 'at' => '2026-11-05 11:00:00']))->assertNotFound();
        $this->getJson(route('traffic.records.index', ['sort' => 'r.timestamp desc']))->assertUnprocessable();
    }

    public function test_bulk_delete_targets_only_selected_composite_keys_and_requires_master(): void
    {
        DB::table('registro_transitos')->insert([
            ['via_transito_id' => 1, 'timestamp' => '2026-11-05 10:00:00', 'veiculos_total' => 42],
            ['via_transito_id' => 2, 'timestamp' => '2026-11-05 10:00:00', 'veiculos_total' => 9],
        ]);
        $selection = ['selected' => ['1|2026-11-05 10:00:00']];
        $this->get(route('traffic.records.index'))->assertOk()->assertDontSee('Excluir selecionadas');
        $this->post(route('traffic.records.destroy-batch'), $selection)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['email' => 'other@example.test']))
            ->post(route('traffic.records.destroy-batch'), $selection)->assertForbidden();
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']))
            ->post(route('traffic.records.destroy-batch'), $selection)->assertRedirect(route('traffic.records.index'));
        $this->get(route('traffic.records.index'))->assertOk()->assertSee('Excluir selecionadas');
        $this->assertDatabaseMissing('registro_transitos', ['via_transito_id' => 1, 'timestamp' => '2026-11-05 10:00:00']);
        $this->assertDatabaseHas('registro_transitos', ['via_transito_id' => 2, 'timestamp' => '2026-11-05 10:00:00']);
        $this->postJson(route('traffic.records.destroy-batch'), ['selected' => ['2|2026-11-05 10:00:00', '2|2026-11-05 10:00:00']])->assertUnprocessable();
        $this->assertDatabaseCount('registro_transitos', 1);
    }

    public function test_edit_page_deletes_only_its_composite_key(): void
    {
        DB::table('registro_transitos')->insert([
            ['via_transito_id' => 1, 'timestamp' => '2026-11-05 10:00:00', 'veiculos_total' => 42],
            ['via_transito_id' => 2, 'timestamp' => '2026-11-05 10:00:00', 'veiculos_total' => 9],
        ]);
        $key = ['via_id' => 1, 'at' => '2026-11-05 10:00:00'];
        $route = route('traffic.records.destroy', $key);
        $this->delete($route)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['email' => 'other@example.test']))->delete($route)->assertForbidden();
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']))
            ->get(route('traffic.records.edit', $key))->assertOk()->assertSee($route);
        $this->delete($route)->assertRedirect(route('traffic.records.index'));
        $this->assertDatabaseMissing('registro_transitos', ['via_transito_id' => 1, 'timestamp' => '2026-11-05 10:00:00']);
        $this->assertDatabaseHas('registro_transitos', ['via_transito_id' => 2, 'timestamp' => '2026-11-05 10:00:00']);
    }
}
