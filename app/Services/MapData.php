<?php

namespace App\Services;

use GeoVeil\Sources\GeoJson;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MapData
{
    public function table(string $schema, string $name): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? "$schema.$name" : $name;
    }

    public function exists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    public function features(iterable $rows, array $fields, bool $points = false): GeoJson
    {
        $features = [];
        foreach ($rows as $row) {
            $geometry = $points
                ? (is_numeric($row->longitude) && is_numeric($row->latitude) ? ['type' => 'Point', 'coordinates' => [(float) $row->longitude, (float) $row->latitude]] : null)
                : (is_string($row->geojson) ? json_decode($row->geojson, true) : $row->geojson);
            if (!$geometry) continue;
            $properties = [];
            foreach ($fields as $field) $properties[$field] = $row->$field ?? null;
            $features[] = ['type' => 'Feature', 'id' => $row->id, 'geometry' => $geometry, 'properties' => $properties];
        }
        return GeoJson::make(['type' => 'FeatureCollection', 'features' => $features]);
    }
}
