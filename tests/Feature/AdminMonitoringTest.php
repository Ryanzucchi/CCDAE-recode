<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ccdae.master_email' => 'master@example.test']);
        Http::fake(['martin:3000/catalog' => Http::response(['tiles' => ['distritos' => [], 'energia_postes' => []]])]);
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id(); $table->string('log_name'); $table->string('description');
            $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('causer_type')->nullable(); $table->unsignedBigInteger('causer_id')->nullable();
            $table->text('properties')->nullable(); $table->timestamps();
        });
        DB::table('activity_log')->insert(['id' => 1, 'log_name' => 'climate_collection', 'description' => 'Coleta concluída', 'subject_type' => 'App\\Models\\Distrito', 'subject_id' => 7, 'properties' => '{"attributes":{"valor":30},"old":{"valor":29}}', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_only_master_reads_activity_and_health_without_modifying_shared_data(): void
    {
        $this->get(route('activity.logs.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['email' => 'other@example.test']));
        $this->get(route('system.health'))->assertForbidden();
        $this->get(route('activity.logs.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']));
        $this->get(route('activity.logs.index', ['canal' => 'climate_collection']))->assertOk()->assertSee('Coleta concluída');
        $this->get(route('activity.logs.show', 1))->assertOk()->assertSee('valor');
        $this->get(route('system.health'))->assertOk()->assertSee('Banco de dados')->assertSee('Coleta concluída')->assertSee('Servidor de mapas Martin')->assertSee('Online');
        $this->assertDatabaseCount('activity_log', 1);
    }

    public function test_queue_clear_requires_master_and_only_deletes_pending_jobs(): void
    {
        $this->assertTrue(Schema::hasTable('jobs'));
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0,
            'reserved_at' => null, 'available_at' => time(), 'created_at' => time()]);
        $this->post(route('system.health.clear-queue'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['email' => 'other@example.test']))
            ->post(route('system.health.clear-queue'))->assertForbidden();
        $this->assertDatabaseCount('jobs', 1);
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']))
            ->post(route('system.health.clear-queue'))->assertRedirect(route('system.health'));
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('activity_log', 1);
    }

    public function test_activity_history_matches_original_columns_sort_and_before_after_details(): void
    {
        $master = User::factory()->create(['email' => 'master@example.test']);
        $operator = User::factory()->create(['name' => 'Operadora Teste']);
        DB::table('activity_log')->where('id', 1)->update([
            'description' => 'updated', 'causer_type' => User::class, 'causer_id' => $operator->id,
            'created_at' => '2026-11-05 10:15:20',
        ]);
        DB::table('activity_log')->insert([
            'id' => 2, 'log_name' => 'default', 'description' => 'created', 'subject_type' => null,
            'properties' => '{}', 'created_at' => '2026-11-06 09:00:00', 'updated_at' => '2026-11-06 09:00:00',
        ]);
        $this->actingAs($master)->get(route('activity.logs.index', ['sort' => 'created_at', 'direction' => 'asc']))
            ->assertOk()->assertSeeInOrder(['10:15:20', '09:00:00'])
            ->assertSee('Operadora Teste')->assertSee('Tarefa de Background')->assertSee('Geral')
            ->assertSee('data-activity-rows', false)->assertSee('data-activity-pagination', false)
            ->assertSee('data-column-toggle="subject_id"', false)
            ->assertSee('data-optional-column="subject_id" hidden', false);
        $this->get(route('activity.logs.show', 1))->assertOk()
            ->assertSee('Detalhes da Ação')->assertSee('Mudanças de Dados')
            ->assertSee('Antes')->assertSee('Depois')->assertSee('Operadora Teste');
        $this->getJson(route('activity.logs.index', ['sort' => 'id']))->assertUnprocessable();
    }

    public function test_activity_bulk_delete_requires_master_and_selected_existing_ids(): void
    {
        DB::table('activity_log')->insert([
            'id' => 2, 'log_name' => 'default', 'description' => 'created', 'properties' => '{}',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $route = route('activity.logs.destroy-batch');
        $this->post($route, ['selected' => [1]])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['email' => 'other@example.test']))
            ->post($route, ['selected' => [1]])->assertForbidden();
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']))
            ->postJson($route, ['selected' => [1, 1]])->assertUnprocessable();
        $this->post($route, ['selected' => [1]])->assertRedirect(route('activity.logs.index'));
        $this->assertDatabaseMissing('activity_log', ['id' => 1]);
        $this->assertDatabaseHas('activity_log', ['id' => 2]);
    }

    public function test_system_health_reads_the_original_workers_shared_cache_signal(): void
    {
        $this->assertTrue(Schema::hasTable('cache'));
        DB::table('cache')->insert([
            'key' => 'laravel-cache-queue_worker_last_seen',
            'value' => 'i:'.now()->timestamp.';', 'expiration' => now()->addMinutes(5)->timestamp,
        ]);
        $this->actingAs(User::factory()->create(['email' => 'master@example.test']))
            ->get(route('system.health'))->assertOk()->assertSee('Worker')->assertSee('Ativo (último sinal: há');
        DB::table('cache')->where('key', 'laravel-cache-queue_worker_last_seen')->update(['expiration' => now()->subMinute()->timestamp]);
        $this->get(route('system.health'))->assertOk()->assertSee('Nenhum sinal do Queue Worker.');
    }

    public function test_master_enqueues_original_collectors_without_executing_them_in_recode(): void
    {
        $route = route('system.health.run-collector');
        $this->post($route)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['email' => 'other@example.test']))
            ->post($route)->assertForbidden();
        $this->assertDatabaseCount('jobs', 0);

        $this->actingAs(User::factory()->create(['email' => 'master@example.test']))
            ->post($route)->assertRedirect(route('system.health'))->assertSessionHas('status', 'Jobs de Coleta (Clima e Trânsito) disparados com sucesso!');
        $jobs = DB::table('jobs')->orderBy('id')->get();
        $this->assertCount(2, $jobs);
        foreach (['App\\Jobs\\CollectClimateData', 'App\\Jobs\\CollectTrafficData'] as $index => $class) {
            $payload = json_decode($jobs[$index]->payload, true);
            $this->assertSame($class, $payload['data']['commandName']);
            $this->assertSame('O:27:"'.$class.'":0:{}', $payload['data']['command']);
        }
        $this->assertSame('default', $jobs[0]->queue);
        $this->assertDatabaseCount('activity_log', 1);
        $this->post($route)->assertRedirect(route('system.health'))
            ->assertSessionHas('status', 'Coleta de clima disparada. A coleta de trânsito já está em andamento ou aguarda na fila.');
        $this->assertDatabaseCount('jobs', 3);
        $this->assertSame(1, DB::table('jobs')->where('payload', 'like', '%CollectTrafficData%')->count());
    }
}
