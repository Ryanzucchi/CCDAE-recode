<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VectorTileTest extends TestCase
{
    public function test_tilejson_rewrites_martin_urls_and_rejects_unknown_sources(): void
    {
        Http::fake(['martin:3000/energia_postes' => Http::response([
            'tilejson' => '3.0.0', 'vector_layers' => [['id' => 'energia_postes']],
            'tiles' => ['http://martin:3000/tiles/energia_postes/{z}/{x}/{y}'],
        ])]);
        $this->get(route('maps.tilejson', 'energia_postes'))->assertOk()
            ->assertJsonPath('tiles.0', url('/tiles/energia_postes/{z}/{x}/{y}'));
        $this->get(route('maps.tilejson', 'not-allowed'))->assertNotFound();
    }

    public function test_vector_tile_proxy_only_accepts_valid_coordinates(): void
    {
        Http::fake(['martin:3000/energia_postes/10/347/557' => Http::response('tile-bytes', 200)]);
        $this->get(route('maps.vector-tile', ['source' => 'energia_postes', 'z' => 10, 'x' => 347, 'y' => 557]))
            ->assertOk()->assertSee('tile-bytes');
        $this->get('/tiles/energia_postes/10/2000/557')->assertNotFound();
    }
}
