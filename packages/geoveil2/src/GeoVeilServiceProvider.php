<?php

namespace GeoVeil;

use Illuminate\Support\ServiceProvider;

final class GeoVeilServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'geoveil');
    }
}
