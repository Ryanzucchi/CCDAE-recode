<?php

namespace GeoVeil\Plugins;

use GeoVeil\Contracts\MapPlugin;

final class Leaflet implements MapPlugin
{
    private ?string $tiles = null;

    private string $attribution = '© OpenStreetMap contributors';

    public static function make(): self
    {
        return new self;
    }

    public function tiles(string $url, string $attribution): self
    {
        $this->tiles = $url;
        $this->attribution = $attribution;

        return $this;
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, ['polygon', 'point', 'line'], true);
    }

    public function jsonSerialize(): array
    {
        return ['id' => 'leaflet', 'tiles' => $this->tiles, 'attribution' => $this->attribution];
    }
}
