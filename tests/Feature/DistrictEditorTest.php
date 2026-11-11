<?php
namespace Tests\Feature;

use App\Models\Distrito;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistrictEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ccdae.master_email' => 'master@example.test']);
    }

    private function master(): User
    {
        return User::factory()->create(['email' => 'master@example.test']);
    }

    private function payload(): array
    {
        return ['nome' => 'Distrito teste', 'cidade' => 'Teste', 'populacao' => 500, 'consumo_medio_kwh' => 40, 'geojson' => json_encode(['type' => 'Polygon', 'coordinates' => [[[0,0],[2,0],[2,2],[0,2],[0,0]]]])];
    }

    public function test_guests_cannot_create_districts(): void
    {
        $this->post('/distritos', $this->payload())->assertRedirect('/login');
        $this->assertDatabaseCount('distritos', 0);
    }

    public function test_authenticated_user_can_create_and_edit_with_computed_centroid(): void
    {
        $this->actingAs($this->master());
        $this->post('/distritos', $this->payload())->assertRedirect();
        $district = Distrito::firstOrFail();
        $this->assertEquals(1, $district->latitude);
        $this->assertEquals(1, $district->longitude);
        $this->get(route('districts.edit', $district))->assertOk()->assertSee('Concluir polígono')->assertSee('Desenhar retângulo')->assertSee('Apagar fronteira');
        $this->put(route('districts.update', $district), array_merge($this->payload(), ['nome' => 'Atualizado', 'latitude' => 80, 'populacao_fonte' => 'Injetado']))->assertRedirect();
        $this->assertSame('Atualizado', $district->fresh()->nome);
        $this->assertEquals(1, $district->fresh()->latitude);
        $this->assertNotSame('Injetado', $district->fresh()->populacao_fonte);
    }

    public function test_master_can_edit_district_inside_map_modal_and_keep_validation(): void
    {
        $this->actingAs($this->master());
        $district = Distrito::query()->create(['nome' => 'Centro', 'cidade' => 'Teste', 'geojson' => ['type' => 'Polygon', 'coordinates' => [[[0,0],[2,0],[2,2],[0,0]]]]]);
        $this->get(route('districts.index', ['cidade' => 'Teste']))->assertOk()->assertSee('data-district-dialog');
        $this->get(route('districts.edit', [$district, 'modal' => 1]))->assertOk()->assertSee('ccdae-modal-content')->assertDontSee('main-navigation');
        $this->put(route('districts.update', [$district, 'modal' => 1]), array_merge($this->payload(), ['nome' => 'Centro editado']))
            ->assertRedirect(route('districts.edit', [$district, 'modal' => 1]));
        $this->get(route('districts.edit', [$district, 'modal' => 1]))->assertOk()->assertSee('ccdae:district-saved');
        $this->put(route('districts.update', [$district, 'modal' => 1]), array_merge($this->payload(), ['nome' => '']))
            ->assertSessionHasErrors('nome');
        $this->assertSame('Centro editado', $district->fresh()->nome);
    }

    public function test_invalid_geometry_and_negative_population_are_rejected(): void
    {
        $this->actingAs($this->master());
        $this->postJson('/distritos', array_merge($this->payload(), ['geojson' => '{"type":"Point","coordinates":[0,0]}']))->assertUnprocessable();
        $this->postJson('/distritos', array_merge($this->payload(), ['populacao' => -1]))->assertUnprocessable();
        $this->assertDatabaseCount('distritos', 0);
    }

    public function test_another_authenticated_account_cannot_edit_or_change_master_password(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'another@example.test']));
        $this->post('/distritos', $this->payload())->assertForbidden();
        $this->get(route('master.password.edit'))->assertForbidden();
        $this->assertDatabaseCount('distritos', 0);
    }

    public function test_master_can_delete_one_or_selected_districts(): void
    {
        $first = Distrito::query()->create(['nome' => 'Primeiro', 'cidade' => 'Teste', 'geojson' => []]);
        $second = Distrito::query()->create(['nome' => 'Segundo', 'cidade' => 'Teste', 'geojson' => []]);
        $third = Distrito::query()->create(['nome' => 'Terceiro', 'cidade' => 'Teste', 'geojson' => []]);
        $this->delete(route('districts.destroy', $first))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['email' => 'another@example.test']))
            ->delete(route('districts.destroy', $first))->assertForbidden();
        $this->actingAs($this->master());
        $this->get(route('districts.index'))->assertOk()->assertSee(route('districts.destroy-batch'))
            ->assertSee('data-column-toggle="created_at"', false)->assertSee('data-column-toggle="updated_at"', false);
        $this->get(route('districts.edit', $first))->assertOk()->assertSee(route('districts.destroy', $first));
        $this->delete(route('districts.destroy', $first))->assertRedirect(route('districts.index'));
        $this->assertDatabaseMissing('distritos', ['id' => $first->id]);
        $this->postJson(route('districts.destroy-batch'), ['selected' => [$second->id, $second->id]])->assertUnprocessable();
        $this->post(route('districts.destroy-batch'), ['selected' => [$second->id]])->assertRedirect(route('districts.index'));
        $this->assertDatabaseMissing('distritos', ['id' => $second->id]);
        $this->assertDatabaseHas('distritos', ['id' => $third->id]);
    }

    public function test_modal_delete_signals_parent_to_refresh(): void
    {
        $district = Distrito::query()->create(['nome' => 'Centro', 'cidade' => 'Teste', 'geojson' => []]);
        $this->actingAs($this->master())->delete(route('districts.destroy', [$district, 'modal' => 1]))
            ->assertOk()->assertSee('ccdae:district-saved');
        $this->assertDatabaseMissing('distritos', ['id' => $district->id]);
    }

    public function test_master_can_change_password_with_current_password(): void
    {
        $user = $this->master();
        $user->password = 'old-test-password'; $user->save();
        $this->actingAs($user);
        $this->put(route('master.password.update'), ['current_password' => 'wrong', 'password' => 'new-long-test-password', 'password_confirmation' => 'new-long-test-password'])->assertSessionHasErrors('current_password');
        $this->put(route('master.password.update'), ['current_password' => 'old-test-password', 'password' => 'new-long-test-password', 'password_confirmation' => 'new-long-test-password'])->assertRedirect();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-long-test-password', $user->fresh()->password));
    }

    public function test_login_and_logout(): void
    {
        $user = $this->master(); $user->password = 'test-password'; $user->save();
        $this->post('/login', ['email' => $user->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'test-password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect();
        $this->assertGuest();
    }
}
