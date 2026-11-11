<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Distrito;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LegacyRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_root_shows_original_dashboard_indicators_to_master(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        Schema::create('temperatura_registrada', function (Blueprint $table) {
            $table->id(); $table->timestamp('timestamp');
        });
        Distrito::create(['nome' => 'Centro', 'cidade' => 'Cáceres', 'geojson' => ['type' => 'Polygon', 'coordinates' => [[[0,0],[1,0],[1,1],[0,0]]]]]);
        DB::table('temperatura_registrada')->insert(['timestamp' => now()]);
        Cache::forget('admin-dashboard-climate-count');
        $user = User::factory()->create(['email' => config('ccdae.master_email')]);
        $this->actingAs($user)->get('/admin')->assertOk()
            ->assertSee('Distritos Cobertos')->assertSee('Registros de Clima')
            ->assertSee('Fila de Processamento')->assertSee('Erros da Fila')
            ->assertSee('Volume de Coleta (Últimos 7 dias)');
        $this->getJson('/admin/indicadores/clima')->assertOk()->assertJsonPath('count', 1);
    }

    public function test_original_module_and_admin_slugs_lead_to_migrated_pages(): void
    {
        foreach ([
        ] as $original => $target) {
            $this->get($original)->assertRedirect($target);
        }
        $this->get('/admin/postes/create')->assertRedirect(route('login'));
        $this->get('/admin/postes/42/edit')->assertRedirect(route('login'));
        $this->get('/admin/via-transitos/create')->assertRedirect(route('login'));
        $this->get('/admin/via-transitos/42/edit')->assertRedirect(route('login'));
        $this->get('/admin/registro-transitos/create')->assertRedirect(route('login'));
        $this->get('/energia/incidentes/create')->assertRedirect(route('login'));
        $this->get('/internet/incidentes/create')->assertRedirect(route('login'));
        $this->get('/transito/infracoes/infracao-transitos/create')->assertRedirect(route('login'));
        foreach (['/admin/mapa-populacao?engine=leaflet', '/admin/mapa-clima', '/admin/mapa-infraestrutura',
            '/admin/mapa-transito', '/admin/distritos', '/transito/mapa-transito'] as $path) {
            $this->get($path)->assertOk();
        }
        $this->get('/admin/mapa-populacao?engine=leaflet')->assertSee('leaflet');
    }

    public function test_original_panel_login_and_logout_paths_use_the_shared_account(): void
    {
        foreach (['admin', 'energia', 'internet', 'transito'] as $panel) {
            $this->get('/'.$panel.'/login')->assertRedirect(route('login'));
            $this->post('/'.$panel.'/logout')->assertRedirect(route('login'));
        }
        $this->actingAs(User::factory()->create(['email' => config('ccdae.master_email')]))
            ->post('/energia/logout')->assertRedirect(route('maps.districts'));
        $this->assertGuest();
    }

    public function test_panel_login_returns_the_master_to_that_panel(): void
    {
        $user = User::factory()->create(['email' => config('ccdae.master_email'), 'password' => 'test-password']);
        $this->get('/energia/login')->assertRedirect(route('login'));
        $this->post('/login', ['email' => $user->email, 'password' => 'test-password'])
            ->assertRedirect(route('energia'));
        $this->assertAuthenticatedAs($user);
    }
}
