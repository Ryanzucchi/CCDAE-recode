<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocodeTest extends TestCase
{
    public function test_geocoding_is_disabled_until_a_provider_is_configured(): void
    {
        config(['geoveil.geocoder_endpoint' => null]);
        Http::preventStrayRequests();
        $this->getJson('/mapas/localizar?q=Caceres')->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_results_are_normalized_cached_and_not_tied_to_mato_grosso(): void
    {
        config(['geoveil.geocoder_endpoint' => 'https://geocoder.example/search']);
        Http::fake(['geocoder.example/*' => Http::response([['display_name' => 'Recife, Pernambuco', 'lon' => '-34.88', 'lat' => '-8.05']])]);
        $this->getJson('/mapas/localizar?q=Recife')->assertOk()->assertJsonPath('results.0.longitude', -34.88);
        $this->getJson('/mapas/localizar?q=Recife')->assertOk();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['q'] === 'Recife');
    }

    public function test_short_queries_never_call_the_provider(): void
    {
        Http::preventStrayRequests();
        $this->getJson('/mapas/localizar?q=ab')->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_an_upstream_failure_becomes_a_useful_error(): void
    {
        config(['geoveil.geocoder_endpoint' => 'https://geocoder.example/search']);
        Http::fake(['*' => Http::response([], 500)]);
        $this->getJson('/mapas/localizar?q=Recife')->assertStatus(503)->assertJsonStructure(['message']);
    }
}
