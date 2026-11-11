<?php

namespace GeoVeil\Layers;

use GeoVeil\Sources\GeoJson;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

abstract class Layer implements \JsonSerializable
{
    protected string $type;

    private Builder|GeoJson|null $source = null;

    private ?array $vector = null;

    private ?string $viewport = null;

    private string $geometry = 'geojson';

    private ?array $coordinates = null;

    private array $properties = [];

    private array $popup = [];

    private ?array $recordAction = null;

    private array $scale = [];

    private array $categories = [];

    private string $color = '#0d9488';

    private string $label;

    private ?string $height = null;

    private ?int $minZoom = null;

    private ?string $flowDirection = null;

    public function __construct(private string $id)
    {
        if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $id) || $id === 'base') {
            throw new InvalidArgumentException('ID de camada inválido ou reservado.');
        }
        $this->label = $id;
    }

    public static function make(string $id): static
    {
        return new static($id);
    }

    public function type(): string
    {
        return $this->type;
    }

    public function source(Builder|GeoJson $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function vectorTiles(string $tilejson, string $sourceLayer, ?array $filter = null): static
    {
        if ($tilejson === '' || $sourceLayer === '') {
            throw new InvalidArgumentException('Mosaico vetorial exige URL e nome da camada.');
        }
        $this->vector = ['tilejson' => $tilejson, 'sourceLayer' => $sourceLayer, 'filter' => $filter];
        return $this;
    }

    public function viewportGeoJson(string $url): static
    {
        if ($url === '') throw new InvalidArgumentException('Fonte espacial exige URL.');
        $this->viewport = $url;
        return $this;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function geometry(string $field): static
    {
        $this->geometry = $field;

        return $this;
    }

    public function coordinates(string $longitude, string $latitude): static
    {
        $this->coordinates = [$longitude, $latitude];

        return $this;
    }

    public function properties(array $fields): static
    {
        $this->properties = $fields;

        return $this;
    }

    public function popup(array $labels): static
    {
        $this->popup = $labels;

        return $this;
    }

    public function recordAction(string $label, string $urlTemplate, bool $modal = false): static
    {
        if ($label === '' || !str_starts_with($urlTemplate, '/') || str_starts_with($urlTemplate, '//') || !str_contains($urlTemplate, '{id}')) {
            throw new InvalidArgumentException('A ação da camada exige rótulo e caminho local com {id}.');
        }
        $this->recordAction = ['label' => $label, 'url' => $urlTemplate, 'modal' => $modal];
        return $this;
    }

    public function color(string $color): static
    {
        self::validateColor($color);
        $this->color = $color;

        return $this;
    }

    public function colorBy(string $field, array $breaks = [10000, 20000, 30000], array $colors = ['#ccfbf1', '#5eead4', '#14b8a6', '#0f766e']): static
    {
        $sorted = $breaks;
        sort($sorted, SORT_NUMERIC);
        if ($sorted !== $breaks || count(array_unique($breaks)) !== count($breaks) || count($colors) !== count($breaks) + 1) {
            throw new InvalidArgumentException('A escala exige limites crescentes e uma cor a mais que os limites.');
        }
        foreach ($breaks as $value) {
            if (! is_numeric($value)) {
                throw new InvalidArgumentException('Limites devem ser numéricos.');
            }
        }
        foreach ($colors as $color) {
            self::validateColor($color);
        }
        $this->scale = compact('field', 'breaks', 'colors');

        return $this;
    }

    public function colorByCategory(string $field, array $colors, array $orderedValues = []): static
    {
        if ($field === '' || count($colors) < 2) {
            throw new InvalidArgumentException('Categorias exigem um campo e pelo menos duas cores.');
        }
        foreach ($colors as $color) self::validateColor($color);
        if (count(array_unique(array_map('strval', $orderedValues))) !== count($orderedValues)) {
            throw new InvalidArgumentException('A ordem das categorias não pode conter valores repetidos.');
        }
        $this->categories = ['field' => $field, 'colors' => array_values($colors), 'orderedValues' => array_values($orderedValues)];
        return $this;
    }

    public function height(string $field): static
    {
        $this->height = $field;

        return $this;
    }

    public function minZoom(int $zoom): static
    {
        if ($zoom < 0 || $zoom > 22) throw new InvalidArgumentException('Zoom mínimo inválido.');
        $this->minZoom = $zoom;
        return $this;
    }

    public function flowDirection(string $field): static
    {
        if ($this->type !== 'line' || $field === '') {
            throw new InvalidArgumentException('O sentido de fluxo exige uma camada de linha e um campo.');
        }
        $this->flowDirection = $field;
        return $this;
    }

    private static function validateColor(string $value): void
    {
        if (! preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            throw new InvalidArgumentException('Use cores no formato #RRGGBB.');
        }
    }

    public function withProperties(array $fields): static
    {
        $this->properties = array_values(array_unique(array_merge($this->properties, $fields)));

        return $this;
    }

    public function jsonSerialize(): array
    {
        if (! $this->source && ! $this->vector && ! $this->viewport) {
            throw new InvalidArgumentException("Defina a fonte da camada {$this->id}.");
        }
        $fields = array_values(array_unique(array_merge($this->properties, array_keys($this->popup), array_filter([$this->scale['field'] ?? null, $this->categories['field'] ?? null, $this->height, $this->flowDirection]))));
        if ($this->vector || $this->viewport) {
            $data = GeoJson::make(['type' => 'FeatureCollection', 'features' => []])->jsonSerialize();
        } elseif ($this->source instanceof Builder) {
            // Explicit allowlist: never serialize an entire Eloquent model to the browser.
            $query = clone $this->source;
            $key = $query->getModel()->getKeyName();
            $columns = array_unique(array_merge([$key], $fields, $this->coordinates ?? [$this->geometry]));
            $limit = $query->getQuery()->limit;
            $rows = $query->limit($limit === null ? 10001 : min($limit, 10001))->get($columns);
            if ($rows->count() > 10000) {
                throw new InvalidArgumentException('Fonte inline limitada a 10.000 registros. Reduza a consulta antes de renderizar.');
            }
            $features = [];
            foreach ($rows as $row) {
                if ($this->coordinates && (! is_numeric($row->{$this->coordinates[0]}) || ! is_numeric($row->{$this->coordinates[1]}))) {
                    throw new InvalidArgumentException('Coordenadas ausentes ou inválidas na camada '.$this->id);
                }
                $geometry = $this->coordinates
                    ? ['type' => 'Point', 'coordinates' => [(float) $row->{$this->coordinates[0]}, (float) $row->{$this->coordinates[1]}]]
                    : $row->{$this->geometry};
                if (is_string($geometry)) {
                    $geometry = json_decode($geometry, true, 512, JSON_THROW_ON_ERROR);
                }
                if (! $geometry) {
                    continue;
                }
                $properties = [];
                foreach ($fields as $field) {
                    $properties[$field] = $row->getAttribute($field);
                }
                $features[] = ['type' => 'Feature', 'id' => $row->getKey(), 'geometry' => $geometry, 'properties' => $properties];
            }
            $data = GeoJson::make(['type' => 'FeatureCollection', 'features' => $features])->jsonSerialize();
        } else {
            $data = $this->source->jsonSerialize();
        }

        $types = match ($this->type) {
            'polygon', 'building' => ['Polygon', 'MultiPolygon'],
            'surface' => ['Polygon', 'MultiPolygon', 'Point'],
            'line' => ['LineString', 'MultiLineString'],
            default => ['Point', 'MultiPoint'],
        };
        foreach ($data['features'] as $feature) {
            if (! in_array($feature['geometry']['type'], $types, true)) {
                throw new InvalidArgumentException('Geometria incompatível com a camada '.$this->id);
            }
        }

        return ['id' => $this->id, 'label' => $this->label, 'type' => $this->type, 'data' => $data, 'vector' => $this->vector, 'viewport' => $this->viewport, 'popup' => $this->popup, 'recordAction' => $this->recordAction, 'color' => $this->color, 'scale' => $this->scale ?: null, 'categories' => $this->categories ?: null, 'height' => $this->height, 'minZoom' => $this->minZoom, 'flowDirection' => $this->flowDirection];
    }
}
