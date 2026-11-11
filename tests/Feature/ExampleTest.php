<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_home_redirects_to_the_district_map(): void
    {
        $this->get('/')
            ->assertRedirect(route('maps.districts'));
    }
}
