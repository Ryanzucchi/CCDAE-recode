<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InternetIncidentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ccdae.master_email' => 'master@example.test']);
        foreach (['antenas', 'cabeamentos', 'equipamentos_infraestrutura'] as $table) {
            Schema::create($table, function (Blueprint $table) {
                $table->id(); $table->unsignedBigInteger('distrito_id')->nullable();
                $table->string('codigo_patrimonio')->nullable(); $table->string('nome')->nullable(); $table->string('tipo_cabo')->nullable();
            });
        }
        Schema::create('incidentes_internet', function (Blueprint $table) {
            $table->id(); $table->string('tipo'); $table->string('causa')->nullable();
            $table->string('severidade'); $table->string('situacao'); $table->text('descricao')->nullable();
            foreach (['distrito_id','antena_id','cabeamento_id','equipamento_id','registrado_por'] as $field) $table->unsignedBigInteger($field)->nullable();
            $table->unsignedInteger('usuarios_afetados')->nullable(); $table->float('latitude'); $table->float('longitude');
            $table->timestamp('ocorrido_em'); $table->timestamp('resolvido_em')->nullable(); $table->timestamps();
        });
    }

    private function payload(): array
    {
        return ['tipo' => 'queda_servico', 'severidade' => 'alta', 'situacao' => 'registrado', 'ocorrido_em' => now()->format('Y-m-d H:i:s'), 'latitude' => -16.07, 'longitude' => -57.68];
    }

    public function test_original_internet_form_paths_remain_after_save(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']));
        $this->get(route('internet.incidents.index'))->assertOk()
            ->assertSee(route('original-occurrence.internet.create'));
        $this->get(route('original-occurrence.internet.create'))->assertOk()
            ->assertSee(route('original-occurrence.internet.store'));
        $this->post(route('original-occurrence.internet.store'), $this->payload())
            ->assertRedirect(route('original-occurrence.internet.edit', 1));
        $this->get(route('original-occurrence.internet.edit', 1))->assertOk()
            ->assertSee(route('original-occurrence.internet.update', 1));
        $this->put(route('original-occurrence.internet.update', 1), $this->payload())
            ->assertRedirect(route('original-occurrence.internet.edit', 1));
        $this->delete(route('original-occurrence.internet.destroy', 1))
            ->assertRedirect(route('internet.incidents.index'));
        $this->assertDatabaseMissing('incidentes_internet', ['id' => 1]);
    }

    public function test_master_creates_and_edits_a_geocoded_incident(): void
    {
        $user = User::factory()->create(['email' => 'master@example.test']);
        $this->actingAs($user);
        $this->get(route('internet.incidents.create'))->assertOk()->assertSee('Ponto afetado')->assertSee('Nova ocorrência');
        $this->post(route('internet.incidents.store'), $this->payload())->assertRedirect();
        $incident = DB::table('incidentes_internet')->first();
        $this->assertSame($user->id, $incident->registrado_por);
        $this->assertEquals(-16.07, $incident->latitude);
        $this->put(route('internet.incidents.update', $incident->id), array_merge($this->payload(), ['situacao' => 'resolvido', 'usuarios_afetados' => 42]))->assertRedirect();
        $this->assertSame('resolvido', DB::table('incidentes_internet')->first()->situacao);
        $this->assertSame(42, DB::table('incidentes_internet')->first()->usuarios_afetados);
    }

    public function test_invalid_coordinates_and_non_master_writes_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']));
        $this->postJson(route('internet.incidents.store'), array_merge($this->payload(), ['latitude' => 200]))->assertUnprocessable();
        $this->actingAs(User::factory()->create(['email' => 'other@example.test']));
        $this->post(route('internet.incidents.store'), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('incidentes_internet', 0);
    }

    public function test_index_filters_situation_and_shows_affected_users(): void
    {
        DB::table('incidentes_internet')->insert([
            ['id' => 1, 'tipo' => 'queda_servico', 'severidade' => 'alta', 'situacao' => 'registrado', 'usuarios_afetados' => 1200, 'ocorrido_em' => '2026-01-01 10:00:00', 'latitude' => -16, 'longitude' => -57],
            ['id' => 2, 'tipo' => 'degradacao', 'severidade' => 'baixa', 'situacao' => 'resolvido', 'usuarios_afetados' => 4, 'ocorrido_em' => '2026-01-02 10:00:00', 'latitude' => -16, 'longitude' => -57],
        ]);
        $this->get(route('internet.incidents.index', ['situacao' => 'registrado']))->assertOk()->assertSee('Usuários afetados')->assertSee('1.200')
            ->assertViewHas('items', fn ($items) => count($items->items()) === 1 && $items->items()[0]->tipo === 'queda_servico');
    }

    public function test_bulk_delete_targets_only_selected_internet_incident(): void
    {
        foreach ([1, 2] as $id) DB::table('incidentes_internet')->insert(['id' => $id, 'tipo' => 'queda_servico', 'severidade' => 'alta', 'situacao' => 'registrado', 'ocorrido_em' => '2026-01-01 10:00:00', 'latitude' => -16, 'longitude' => -57]);
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']))
            ->post(route('internet.incidents.destroy-batch'), ['selected' => [2]])->assertRedirect(route('internet.incidents.index'));
        $this->assertDatabaseHas('incidentes_internet', ['id' => 1]);
        $this->assertDatabaseMissing('incidentes_internet', ['id' => 2]);
    }

    public function test_edit_page_deletes_one_internet_incident_for_master(): void
    {
        foreach ([1, 2] as $id) DB::table('incidentes_internet')->insert(['id' => $id, 'tipo' => 'queda_servico', 'severidade' => 'alta', 'situacao' => 'registrado', 'ocorrido_em' => '2026-01-01 10:00:00', 'latitude' => -16, 'longitude' => -57]);
        $route = route('internet.incidents.destroy', 1);
        $this->delete($route)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['email' => 'other@example.test']))->delete($route)->assertForbidden();
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']))
            ->get(route('internet.incidents.edit', 1))->assertOk()->assertSee($route);
        $this->delete($route)->assertRedirect(route('internet.incidents.index'));
        $this->assertDatabaseMissing('incidentes_internet', ['id' => 1]);
        $this->assertDatabaseHas('incidentes_internet', ['id' => 2]);
    }
}
