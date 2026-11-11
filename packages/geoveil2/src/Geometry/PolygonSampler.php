<?php

namespace GeoVeil\Geometry;

class PolygonSampler
{
    public function sample(mixed $geometry, float $fallbackLng, float $fallbackLat, float $cellSize = 0.0022, int $minimum = 3, int $maximum = 8): array
    {
        if (is_string($geometry)) {
            $geometry = json_decode($geometry, true);
        }
        if (! is_array($geometry)) {
            return [[$fallbackLng, $fallbackLat]];
        }

        $points = $this->points($geometry['coordinates'] ?? []);
        if ($points === []) {
            return [[$fallbackLng, $fallbackLat]];
        }

        $minLng = min(array_column($points, 0));
        $maxLng = max(array_column($points, 0));
        $minLat = min(array_column($points, 1));
        $maxLat = max(array_column($points, 1));
        $lngSpan = max(0.0001, $maxLng - $minLng);
        $latSpan = max(0.0001, $maxLat - $minLat);
        $columns = max($minimum, min($maximum, (int) ceil($lngSpan / $cellSize)));
        $rows = max($minimum, min($maximum, (int) ceil($latSpan / $cellSize)));
        $samples = [];
        for ($row = 0; $row < $rows; $row++) {
            for ($column = 0; $column < $columns; $column++) {
                $candidate = [$minLng + (($column + .5) / $columns) * $lngSpan, $minLat + (($row + .5) / $rows) * $latSpan];
                if ($this->contains($candidate, $geometry)) {
                    $samples[] = $candidate;
                }
            }
        }

        return $samples ?: [[array_sum(array_column($points, 0)) / count($points), array_sum(array_column($points, 1)) / count($points)]];
    }

    private function points(array $coordinates): array
    {
        $points = [];
        $walk = function ($value) use (&$walk, &$points): void {
            if (is_array($value) && count($value) >= 2 && is_numeric($value[0]) && is_numeric($value[1])) {
                $points[] = [(float) $value[0], (float) $value[1]];

                return;
            }
            if (is_array($value)) {
                foreach ($value as $child) {
                    $walk($child);
                }
            }
        };
        $walk($coordinates);

        return $points;
    }

    private function contains(array $point, array $geometry): bool
    {
        $polygons = match ($geometry['type'] ?? null) {
            'Polygon' => [$geometry['coordinates'] ?? []], 'MultiPolygon' => $geometry['coordinates'] ?? [], default => []
        };
        foreach ($polygons as $polygon) {
            if (! isset($polygon[0]) || ! $this->insideRing($point, $polygon[0])) {
                continue;
            }
            foreach (array_slice($polygon, 1) as $hole) {
                if ($this->insideRing($point, $hole)) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    private function insideRing(array $point, array $ring): bool
    {
        $inside = false;
        for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            if ((($yi > $point[1]) !== ($yj > $point[1])) && $point[0] < ($xj - $xi) * ($point[1] - $yi) / (($yj - $yi) ?: 1e-12) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
