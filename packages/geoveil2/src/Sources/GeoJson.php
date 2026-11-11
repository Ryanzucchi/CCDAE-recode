<?php

namespace GeoVeil\Sources;

use InvalidArgumentException;

final class GeoJson implements \JsonSerializable
{
    private function __construct(private array $data) {}

    public static function make(array $data): self
    {
        if (($data['type'] ?? null) !== 'FeatureCollection' || ! is_array($data['features'] ?? null)) {
            throw new InvalidArgumentException('A fonte deve ser uma FeatureCollection GeoJSON.');
        }
        if (! array_is_list($data['features'])) {
            throw new InvalidArgumentException('Features devem ser uma lista.');
        }
        $ids = [];
        foreach ($data['features'] as $index => &$feature) {
            if (($feature['type'] ?? null) !== 'Feature' || ! is_array($feature['geometry'] ?? null)) {
                throw new InvalidArgumentException("Feature inválida na posição {$index}.");
            }
            self::validateGeometry($feature['geometry']);
            $feature['id'] ??= $index;
            if (! is_string($feature['id']) && ! is_int($feature['id'])) {
                throw new InvalidArgumentException('ID inválido.');
            }
            if (isset($ids[(string) $feature['id']])) {
                throw new InvalidArgumentException('IDs de features devem ser únicos.');
            }
            $ids[(string) $feature['id']] = true;
            $feature['properties'] ??= [];
            if (! is_array($feature['properties'])) {
                throw new InvalidArgumentException('Propriedades inválidas.');
            }
        }
        unset($feature);

        return new self($data);
    }

    public static function validateGeometry(array $geometry): void
    {
        $type = $geometry['type'] ?? '';
        $coordinates = $geometry['coordinates'] ?? null;
        if (! is_array($coordinates) || ! array_is_list($coordinates) || ! $coordinates) {
            throw new InvalidArgumentException('Coordenadas ausentes ou inválidas.');
        }
        $position = static function ($point): void {
            if (! is_array($point) || count($point) < 2 || ! is_numeric($point[0]) || ! is_numeric($point[1]) || ! is_finite((float) $point[0]) || ! is_finite((float) $point[1]) || abs((float) $point[0]) > 180 || abs((float) $point[1]) > 90) {
                throw new InvalidArgumentException('Posição deve conter longitude e latitude válidas.');
            }
        };
        $line = static function ($points, bool $ring = false) use ($position): void {
            if (! is_array($points) || count($points) < ($ring ? 4 : 2)) {
                throw new InvalidArgumentException('Linha ou anel inválido.');
            }
            foreach ($points as $point) {
                $position($point);
            }
            if ($ring && $points[0] != $points[count($points) - 1]) {
                throw new InvalidArgumentException('O polígono deve ter um anel fechado.');
            }
        };
        $polygon = static function ($rings) use ($line): void {
            if (! is_array($rings) || ! $rings) {
                throw new InvalidArgumentException('Polígono sem anéis.');
            }
            foreach ($rings as $ring) {
                $line($ring, true);
            }
        };
        switch ($type) {
            case 'Point': $position($coordinates);
                break;
            case 'MultiPoint': foreach ($coordinates as $point) {
                $position($point);
            } break;
            case 'LineString': $line($coordinates);
                break;
            case 'MultiLineString': foreach ($coordinates as $points) {
                $line($points);
            } break;
            case 'Polygon': $polygon($coordinates);
                break;
            case 'MultiPolygon': foreach ($coordinates as $rings) {
                $polygon($rings);
            } break;
            default: throw new InvalidArgumentException('Tipo de geometria não suportado.');
        }
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
