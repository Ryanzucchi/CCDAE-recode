<?php

namespace GeoVeil\Plugins;

use GeoVeil\Contracts\MapPlugin;

final class MapLibre implements MapPlugin
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
        return in_array($capability, ['polygon', 'point', 'line', 'heatmap', 'building', 'surface'], true);
    }

    public function jsonSerialize(): array
    {
        return ['id' => 'maplibre', 'tiles' => $this->tiles, 'attribution' => $this->attribution];
    }
}
