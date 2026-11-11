<?php

namespace App\Services;

use GeoVeil\Sources\GeoJson;

class DistrictMapFeatures
{
    public function build(iterable $districts): array
    {
        $polygons = [];
        $points = [];
        $missing = [];
        foreach ($districts as $district) {
            $properties = ['id' => $district->id, 'nome' => $district->nome, 'cidade' => $district->cidade, 'populacao' => $district->populacao];
            $geometry = is_string($district->geojson) ? json_decode($district->geojson, true) : $district->geojson;
            if (in_array($geometry['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
                $polygons[] = $this->feature($district->id, $geometry, $properties);
            } elseif (is_numeric($district->longitude) && is_numeric($district->latitude)) {
                $missing[] = ['id' => $district->id, 'coordinate' => [(float) $district->longitude, (float) $district->latitude], 'properties' => $properties];
            }
        }
        if (count($missing) > 1) {
            $longitudes = array_column(array_column($missing, 'coordinate'), 0);
            $latitudes = array_column(array_column($missing, 'coordinate'), 1);
            $west = min($longitudes); $east = max($longitudes);
            $south = min($latitudes); $north = max($latitudes);
            $longitudePadding = ($east - $west) * .1 ?: .05;
            $latitudePadding = ($north - $south) * .1 ?: .05;
            $box = [
                [$west - $longitudePadding, $south - $latitudePadding],
                [$east + $longitudePadding, $south - $latitudePadding],
                [$east + $longitudePadding, $north + $latitudePadding],
                [$west - $longitudePadding, $north + $latitudePadding],
            ];
            foreach ($missing as $index => $site) {
                $cell = $box;
                foreach ($missing as $otherIndex => $other) {
                    if ($index === $otherIndex) continue;
                    $cell = $this->clip($cell, $site['coordinate'], $other['coordinate']);
                    if (count($cell) < 3) break;
                }
                if (count($cell) >= 3) {
                    $cell[] = $cell[0];
                    $polygons[] = $this->feature($site['id'], ['type' => 'Polygon', 'coordinates' => [$cell]], $site['properties']);
                } else {
                    $points[] = $this->feature($site['id'], ['type' => 'Point', 'coordinates' => $site['coordinate']], $site['properties']);
                }
            }
        } elseif ($missing) {
            $site = $missing[0];
            $points[] = $this->feature($site['id'], ['type' => 'Point', 'coordinates' => $site['coordinate']], $site['properties']);
        }
        return [GeoJson::make(['type' => 'FeatureCollection', 'features' => $polygons]), GeoJson::make(['type' => 'FeatureCollection', 'features' => $points])];
    }

    private function feature(int $id, array $geometry, array $properties): array
    {
        return ['type' => 'Feature', 'id' => $id, 'geometry' => $geometry, 'properties' => $properties];
    }

    private function clip(array $polygon, array $site, array $other): array
    {
        $normal = [$other[0] - $site[0], $other[1] - $site[1]];
        $middle = [($site[0] + $other[0]) / 2, ($site[1] + $other[1]) / 2];
        $signed = fn ($point) => ($point[0] - $middle[0]) * $normal[0] + ($point[1] - $middle[1]) * $normal[1];
        $result = [];
        foreach ($polygon as $index => $end) {
            $start = $polygon[($index + count($polygon) - 1) % count($polygon)];
            $before = $signed($start); $after = $signed($end);
            if (($before <= 0) !== ($after <= 0)) {
                $factor = $before / ($before - $after);
                $result[] = [$start[0] + ($end[0] - $start[0]) * $factor, $start[1] + ($end[1] - $start[1]) * $factor];
            }
            if ($after <= 0) $result[] = $end;
        }
        return $result;
    }
}
