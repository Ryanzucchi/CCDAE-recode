<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TrafficInfractionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ccdae.master_email' => 'master@example.test']);
        Schema::create('via_transitos', function (Blueprint $table) { $table->id(); $table->string('nome'); });
        Schema::create('infracoes_transito', function (Blueprint $table) {
            $table->id(); $table->string('tipo'); $table->string('codigo')->nullable(); $table->string('placa')->nullable();
            $table->string('situacao'); $table->text('descricao')->nullable(); $table->decimal('valor')->nullable();
            $table->unsignedSmallInteger('pontos'); $table->unsignedBigInteger('via_transito_id')->nullable();
            $table->unsignedBigInteger('distrito_id')->nullable(); $table->unsignedBigInteger('registrado_por')->nullable();
            $table->decimal('latitude'); $table->decimal('longitude'); $table->timestamp('ocorrido_em'); $table->timestamps();
        });
    }

    private function payload(): array
    {
        return ['tipo' => 'Excesso de velocidade', 'ocorrido_em' => now()->format('Y-m-d H:i:s'),
            'situacao' => 'registrada', 'pontos' => 5, 'latitude' => -16.07, 'longitude' => -57.68];
    }

    public function test_original_traffic_infraction_paths_remain_after_save(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']));
        $this->get(route('original-occurrence.transito.index'))->assertOk()
            ->assertSee(route('original-occurrence.transito.create'));
        $this->get(route('original-occurrence.transito.create'))->assertOk()
            ->assertSee(route('original-occurrence.transito.store'))
            ->assertSee('maxlength="80"', false)
            ->assertSee('maxlength="40"', false)
            ->assertSee('maxlength="10"', false)
            ->assertSee('max="20"', false)
            ->assertSee('maxlength="500"', false)
            ->assertSee('data-geoveil-select-filter="gv-traffic-infraction-distrito_id"', false);
        $this->post(route('original-occurrence.transito.store'), $this->payload())
            ->assertRedirect(route('original-occurrence.transito.edit', 1));
        $this->get(route('original-occurrence.transito.edit', 1))->assertOk()
            ->assertSee(route('original-occurrence.transito.update', 1));
        $this->put(route('original-occurrence.transito.update', 1), $this->payload())
            ->assertRedirect(route('original-occurrence.transito.edit', 1));
        $this->delete(route('original-occurrence.transito.destroy', 1))
            ->assertRedirect(route('original-occurrence.transito.index'));
        $this->assertDatabaseMissing('infracoes_transito', ['id' => 1]);
    }

    public function test_master_creates_and_edits_geocoded_infraction(): void
    {
        $user = User::factory()->create(['email' => 'master@example.test']);
        $this->actingAs($user);
        $this->get(route('traffic.infractions.create'))->assertOk()->assertSee('Local da infração');
        $this->post(route('traffic.infractions.store'), $this->payload())->assertRedirect();
        $infraction = DB::table('infracoes_transito')->first();
        $this->assertSame($user->id, $infraction->registrado_por);
        $this->put(route('traffic.infractions.update', $infraction->id), array_merge($this->payload(), ['situacao' => 'notificada', 'pontos' => 7]))->assertRedirect();
        $this->assertSame('notificada', DB::table('infracoes_transito')->first()->situacao);
        $this->assertSame(7, DB::table('infracoes_transito')->first()->pontos);
    }

    public function test_invalid_points_and_non_master_writes_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']));
        $this->postJson(route('traffic.infractions.store'), array_merge($this->payload(), ['pontos' => 21]))->assertUnprocessable();
        $this->actingAs(User::factory()->create(['email' => 'other@example.test']));
        $this->post(route('traffic.infractions.store'), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('infracoes_transito', 0);
    }

    public function test_index_searches_road_and_sorts_original_columns(): void
    {
        DB::table('via_transitos')->insert(['id' => 1, 'nome' => 'Rua das Flores']);
        DB::table('infracoes_transito')->insert([
            ['id' => 1, 'tipo' => 'Velocidade', 'placa' => 'ABC1234', 'situacao' => 'registrada', 'valor' => 100, 'pontos' => 4, 'via_transito_id' => 1, 'ocorrido_em' => '2026-01-01 10:00:00', 'latitude' => -16, 'longitude' => -57],
            ['id' => 2, 'tipo' => 'Estacionamento', 'placa' => 'XYZ9876', 'situacao' => 'paga', 'valor' => 200, 'pontos' => 3, 'via_transito_id' => null, 'ocorrido_em' => '2026-01-02 10:00:00', 'latitude' => -16, 'longitude' => -57],
        ]);
        $this->get(route('traffic.infractions.index', ['q' => 'Flores']))->assertOk()->assertSee('Rua das Flores')
            ->assertSee('<span class="ccdae-table-badge">registrada</span>', false)
            ->assertViewHas('items', fn ($items) => count($items->items()) === 1 && $items->items()[0]->placa === 'ABC1234');
        $this->get(route('traffic.infractions.index', ['sort' => 'valor', 'direction' => 'desc']))->assertOk()
            ->assertViewHas('items', fn ($items) => $items->items()[0]->tipo === 'Estacionamento');
    }

    public function test_bulk_delete_targets_only_selected_infraction(): void
    {
        foreach ([1, 2] as $id) DB::table('infracoes_transito')->insert(['id' => $id, 'tipo' => 'Velocidade', 'situacao' => 'registrada', 'pontos' => 4, 'ocorrido_em' => '2026-01-01 10:00:00', 'latitude' => -16, 'longitude' => -57]);
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']))
            ->post(route('traffic.infractions.destroy-batch'), ['selected' => [1]])->assertRedirect(route('traffic.infractions.index'));
        $this->assertDatabaseMissing('infracoes_transito', ['id' => 1]);
        $this->assertDatabaseHas('infracoes_transito', ['id' => 2]);
    }

    public function test_edit_page_deletes_one_infraction_for_master(): void
    {
        foreach ([1, 2] as $id) DB::table('infracoes_transito')->insert(['id' => $id, 'tipo' => 'Excesso de velocidade', 'situacao' => 'registrada', 'pontos' => 5, 'ocorrido_em' => '2026-01-01 10:00:00', 'latitude' => -16, 'longitude' => -57]);
        $route = route('traffic.infractions.destroy', 1);
        $this->delete($route)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['email' => 'other@example.test']))->delete($route)->assertForbidden();
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']))
            ->get(route('traffic.infractions.edit', 1))->assertOk()->assertSee($route);
        $this->delete($route)->assertRedirect(route('traffic.infractions.index'));
        $this->assertDatabaseMissing('infracoes_transito', ['id' => 1]);
        $this->assertDatabaseHas('infracoes_transito', ['id' => 2]);
    }
}
