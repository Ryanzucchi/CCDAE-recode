<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EnergyIncidentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ccdae.master_email' => 'master@example.test']);
        foreach (['postes', 'cabeamentos', 'equipamentos_infraestrutura', 'centrais_distribuicao'] as $table) {
            Schema::create($table, function (Blueprint $table) {
                $table->id(); $table->unsignedBigInteger('distrito_id')->nullable();
                $table->string('codigo_patrimonio')->nullable(); $table->string('nome')->nullable();
            });
        }
        Schema::create('incidentes_energia', function (Blueprint $table) {
            $table->id(); $table->string('tipo'); $table->string('causa')->nullable();
            $table->string('severidade'); $table->string('situacao'); $table->text('descricao')->nullable();
            foreach (['distrito_id','poste_id','cabeamento_id','equipamento_id','central_id','registrado_por'] as $field) $table->unsignedBigInteger($field)->nullable();
            $table->float('latitude'); $table->float('longitude');
            $table->timestamp('ocorrido_em'); $table->timestamp('resolvido_em')->nullable(); $table->timestamps();
        });
    }

    private function payload(): array
    {
        return ['tipo' => 'interrupcao', 'severidade' => 'alta', 'situacao' => 'registrado', 'ocorrido_em' => now()->format('Y-m-d H:i:s'), 'latitude' => -16.07, 'longitude' => -57.68];
    }

    public function test_original_energy_form_paths_remain_after_save(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']));
        $this->get(route('energy.incidents.index'))->assertOk()
            ->assertSee(route('original-occurrence.energia.create'));
        $this->get(route('original-occurrence.energia.create'))->assertOk()
            ->assertSee(route('original-occurrence.energia.store'))
            ->assertSee('data-endpoint="'.route('resource.lookup', 'cabeamentos').'"', false);
        $this->post(route('original-occurrence.energia.store'), $this->payload())
            ->assertRedirect(route('original-occurrence.energia.edit', 1));
        $this->get(route('original-occurrence.energia.edit', 1))->assertOk()
            ->assertSee(route('original-occurrence.energia.update', 1));
        $this->put(route('original-occurrence.energia.update', 1), $this->payload())
            ->assertRedirect(route('original-occurrence.energia.edit', 1));
        $this->delete(route('original-occurrence.energia.destroy', 1))
            ->assertRedirect(route('energy.incidents.index'));
        $this->assertDatabaseMissing('incidentes_energia', ['id' => 1]);
    }

    public function test_master_creates_and_edits_a_geocoded_incident(): void
    {
        $user = User::factory()->create(['email' => 'master@example.test']);
        $this->actingAs($user);
        $this->get(route('energy.incidents.create'))->assertOk()->assertSee('Ponto afetado')->assertSee('Nova ocorrência');
        $this->post(route('energy.incidents.store'), $this->payload())->assertRedirect();
        $incident = DB::table('incidentes_energia')->first();
        $this->assertSame($user->id, $incident->registrado_por);
        $this->assertEquals(-16.07, $incident->latitude);
        $this->put(route('energy.incidents.update', $incident->id), array_merge($this->payload(), ['situacao' => 'resolvido']))->assertRedirect();
        $this->assertSame('resolvido', DB::table('incidentes_energia')->first()->situacao);
    }

    public function test_invalid_coordinates_and_non_master_writes_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']));
        $this->postJson(route('energy.incidents.store'), array_merge($this->payload(), ['latitude' => 200]))->assertUnprocessable();
        $this->actingAs(User::factory()->create(['email' => 'other@example.test']));
        $this->post(route('energy.incidents.store'), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('incidentes_energia', 0);
    }

    public function test_index_matches_original_status_filter_columns_and_sorting(): void
    {
        DB::table('incidentes_energia')->insert([
            ['id' => 1, 'tipo' => 'surto', 'causa' => 'clima', 'severidade' => 'alta', 'situacao' => 'registrado', 'ocorrido_em' => '2026-01-01 10:00:00', 'latitude' => -16, 'longitude' => -57],
            ['id' => 2, 'tipo' => 'interrupcao', 'causa' => 'acidente', 'severidade' => 'baixa', 'situacao' => 'resolvido', 'ocorrido_em' => '2026-01-02 10:00:00', 'latitude' => -16, 'longitude' => -57],
        ]);
        $this->get(route('energy.incidents.index', ['situacao' => 'registrado']))->assertOk()->assertSee('Causa')->assertSee('Distrito')
            ->assertSee('<span class="ccdae-table-badge">surto</span>', false)
            ->assertSee('<span class="ccdae-table-badge">clima</span>', false)
            ->assertViewHas('items', fn ($items) => count($items->items()) === 1 && $items->items()[0]->tipo === 'surto');
        $this->get(route('energy.incidents.index', ['sort' => 'severidade', 'direction' => 'asc']))->assertOk()
            ->assertViewHas('items', fn ($items) => $items->items()[0]->tipo === 'surto');
    }

    public function test_bulk_delete_requires_master_and_only_removes_selected_energy_incidents(): void
    {
        foreach ([1, 2] as $id) DB::table('incidentes_energia')->insert(['id' => $id, 'tipo' => 'surto', 'severidade' => 'alta', 'situacao' => 'registrado', 'ocorrido_em' => '2026-01-01 10:00:00', 'latitude' => -16, 'longitude' => -57]);
        $this->get(route('energy.incidents.index'))->assertOk()->assertDontSee('Excluir selecionados');
        $this->actingAs(User::factory()->create(['email' => 'other@example.test']))->post(route('energy.incidents.destroy-batch'), ['selected' => [1]])->assertForbidden();
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']))->postJson(route('energy.incidents.destroy-batch'), ['selected' => [1, 1]])->assertUnprocessable();
        $this->get(route('energy.incidents.index'))->assertOk()->assertSee('Excluir selecionados');
        $this->post(route('energy.incidents.destroy-batch'), ['selected' => [1]])->assertRedirect(route('energy.incidents.index'));
        $this->assertDatabaseMissing('incidentes_energia', ['id' => 1]);
        $this->assertDatabaseHas('incidentes_energia', ['id' => 2]);
    }

    public function test_edit_page_deletes_one_energy_incident_for_master(): void
    {
        foreach ([1, 2] as $id) DB::table('incidentes_energia')->insert(['id' => $id, 'tipo' => 'surto', 'severidade' => 'alta', 'situacao' => 'registrado', 'ocorrido_em' => '2026-01-01 10:00:00', 'latitude' => -16, 'longitude' => -57]);
        $route = route('energy.incidents.destroy', 1);
        $this->delete($route)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['email' => 'other@example.test']))->delete($route)->assertForbidden();
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']))
            ->get(route('energy.incidents.edit', 1))->assertOk()->assertSee($route);
        $this->delete($route)->assertRedirect(route('energy.incidents.index'));
        $this->assertDatabaseMissing('incidentes_energia', ['id' => 1]);
        $this->assertDatabaseHas('incidentes_energia', ['id' => 2]);
    }
}
