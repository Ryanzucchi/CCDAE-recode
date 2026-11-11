<?php

namespace GeoVeil;

use GeoVeil\Contracts\MapPlugin;
use GeoVeil\Layers\Layer;
use InvalidArgumentException;

final class Map implements \JsonSerializable
{
    private ?MapPlugin $plugin = null;

    private array $layers = [];

    private array $filters = [];

    private array $widgets = [];

    private array $center = [0, 0];

    private float $zoom = 2;

    private string $title = 'Explorar território';

    private bool $search = true;

    private string $theme = 'light';

    private string $layout = 'workspace';

    private bool $fitOnLoad = false;

    private bool $fitOnSelect = true;

    private ?string $geocoder = null;

    private function __construct(private string $id) {}

    public static function make(string $id): self
    {
        if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $id)) {
            throw new InvalidArgumentException('ID de mapa inválido.');
        }

        return new self($id);
    }

    public function plugin(MapPlugin $plugin): self
    {
        $this->plugin = $plugin;

        return $this;
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function center(float $longitude, float $latitude): self
    {
        if (! is_finite($longitude) || ! is_finite($latitude) || abs($longitude) > 180 || abs($latitude) > 90) {
            throw new InvalidArgumentException('Coordenadas inválidas.');
        }
        $this->center = [$longitude, $latitude];

        return $this;
    }

    public function zoom(float $zoom): self
    {
        if ($zoom < 0 || $zoom > 22 || ! is_finite($zoom)) {
            throw new InvalidArgumentException('Zoom inválido.');
        } $this->zoom = $zoom;

        return $this;
    }

    public function layers(array $layers): self
    {
        $this->layers = $layers;

        return $this;
    }

    public function filters(array $filters): self
    {
        $this->filters = $filters;

        return $this;
    }

    public function widgets(array $widgets): self
    {
        $this->widgets = $widgets;

        return $this;
    }

    public function searchable(bool $value = true): self
    {
        $this->search = $value;

        return $this;
    }

    public function geocoder(string $endpoint): self
    {
        $this->geocoder = $endpoint;

        return $this;
    }

    public function theme(string $theme): self
    {
        if (! in_array($theme, ['light', 'dark'], true)) {
            throw new InvalidArgumentException('Tema inválido.');
        }
        $this->theme = $theme;

        return $this;
    }

    public function layout(string $layout): self
    {
        if (! in_array($layout, ['workspace', 'map', 'embedded'], true)) {
            throw new InvalidArgumentException('Layout inválido.');
        }
        $this->layout = $layout;

        return $this;
    }

    public function fitOnLoad(bool $value = true): self
    {
        $this->fitOnLoad = $value;

        return $this;
    }

    public function fitOnSelect(bool $value = true): self
    {
        $this->fitOnSelect = $value;

        return $this;
    }

    public function toArray(): array
    {
        if (! $this->plugin) {
            throw new InvalidArgumentException('Escolha o plugin do mapa.');
        }
        $layers = [];
        $ids = [];
        foreach ($this->layers as $layer) {
            if (! $layer instanceof Layer) {
                throw new InvalidArgumentException('Camada inválida.');
            }
            if (! $this->plugin->supports($layer->type())) {
                throw new InvalidArgumentException("O plugin não suporta a camada {$layer->type()}.");
            }
            $definition = $layer->jsonSerialize();
            if ($definition['vector'] && $this->plugin->jsonSerialize()['id'] !== 'maplibre') {
                throw new InvalidArgumentException('Mosaicos vetoriais exigem o plugin MapLibre.');
            }
            if (isset($ids[$definition['id']])) {
                throw new InvalidArgumentException('IDs de camadas devem ser únicos.');
            }
            $ids[$definition['id']] = true;
            $layers[] = $definition;
        }
        $filters = array_map(fn ($item) => $item->jsonSerialize(), $this->filters);
        $widgets = array_map(fn ($item) => $item->jsonSerialize(), $this->widgets);
        foreach (array_merge($filters, $widgets) as $item) {
            if (isset($item['layer']) && ! isset($ids[$item['layer']])) {
                throw new InvalidArgumentException('Componente referencia uma camada inexistente.');
            }
        }
        foreach ($widgets as $item) {
            if (! isset($item['layer']) || ($item['type'] === 'stat' && ! isset($item['operation'])) || ($item['type'] === 'bar' && ! isset($item['labelField'], $item['field'])) || ($item['type'] === 'line' && ! isset($item['time'], $item['field']))) {
                throw new InvalidArgumentException('Configure a fonte do indicador.');
            }
        }

        return ['version' => 2, 'theme' => $this->theme, 'layout' => $this->layout, 'fitOnLoad' => $this->fitOnLoad, 'fitOnSelect' => $this->fitOnSelect, 'id' => $this->id, 'title' => $this->title, 'plugin' => $this->plugin->jsonSerialize(), 'center' => $this->center, 'zoom' => $this->zoom, 'search' => $this->search, 'geocoder' => $this->geocoder, 'layers' => $layers, 'filters' => $filters, 'widgets' => $widgets];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
