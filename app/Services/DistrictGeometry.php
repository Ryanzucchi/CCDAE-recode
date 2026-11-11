<?php

namespace App\Services;

use GeoVeil\Sources\GeoJson;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DistrictGeometry
{
    public function prepare(string $json, ?int $ignoreId = null): array
    {
        try {
            $geometry = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!in_array($geometry['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) throw new \InvalidArgumentException();
            GeoJson::make(['type' => 'FeatureCollection', 'features' => [['type' => 'Feature', 'id' => 1, 'geometry' => $geometry, 'properties' => []]]])->jsonSerialize();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['geojson' => 'Desenhe um polígono válido e feche sua fronteira.']);
        }
        if (DB::getDriverName() === 'pgsql') {
            $valid = DB::selectOne('SELECT ST_IsValid(ST_GeomFromGeoJSON(?)) AS valid', [$json]);
            if (!$valid->valid) throw ValidationException::withMessages(['geojson' => 'A fronteira se cruza ou contém uma geometria inválida.']);
            $result = DB::selectOne('WITH shape AS (SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) AS geom), others AS (SELECT ST_Union(ST_SetSRID(ST_GeomFromGeoJSON(geojson::text),4326)) AS geom FROM geo.distritos WHERE geojson IS NOT NULL AND (?::bigint IS NULL OR id <> ?::bigint)), clipped AS (SELECT CASE WHEN others.geom IS NULL THEN shape.geom ELSE ST_Difference(shape.geom, ST_Buffer(others.geom,0.00002)) END AS geom FROM shape, others) SELECT ST_AsGeoJSON(geom) AS geometry, ST_X(ST_Centroid(geom)) AS longitude, ST_Y(ST_Centroid(geom)) AS latitude, ST_IsEmpty(geom) AS empty FROM clipped', [$json, $ignoreId, $ignoreId]);
            if ($result->empty) throw ValidationException::withMessages(['geojson' => 'A área está totalmente ocupada por outro distrito. Ajuste a fronteira.']);
            return ['geojson' => json_decode($result->geometry, true), 'longitude' => $result->longitude, 'latitude' => $result->latitude];
        }
        // SQLite test/local mode: area-weighted centroid, including holes.
        $polygons = $geometry['type'] === 'Polygon' ? [$geometry['coordinates']] : $geometry['coordinates'];
        $area = $x = $y = 0;
        foreach ($polygons as $polygon) foreach ($polygon as $index => $ring) {
            $twiceArea = $cx = $cy = 0;
            for ($i = 0; $i < count($ring) - 1; $i++) {
                [$a, $b] = [$ring[$i], $ring[$i + 1]];
                $cross = $a[0] * $b[1] - $b[0] * $a[1];
                $twiceArea += $cross; $cx += ($a[0] + $b[0]) * $cross; $cy += ($a[1] + $b[1]) * $cross;
            }
            if (abs($twiceArea) < 1e-12) continue;
            $weight = abs($twiceArea) * ($index === 0 ? 1 : -1);
            $area += $weight; $x += $cx / (3 * $twiceArea) * $weight; $y += $cy / (3 * $twiceArea) * $weight;
        }
        if ($area <= 0) throw ValidationException::withMessages(['geojson' => 'O polígono precisa ter área maior que zero.']);
        return ['geojson' => $geometry, 'longitude' => $x / $area, 'latitude' => $y / $area];
    }
}
