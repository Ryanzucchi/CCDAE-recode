<?php

namespace App\Http\Controllers\Map;

use App\Http\Controllers\Controller;
use App\Services\MapData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BuildingViewportController extends Controller
{
    public function __invoke(Request $request, MapData $data)
    {
        $input = $request->validate([
            'west' => 'required|numeric|between:-180,180', 'south' => 'required|numeric|between:-90,90',
            'east' => 'required|numeric|between:-180,180', 'north' => 'required|numeric|between:-90,90',
        ]);
        $bbox = array_map(fn ($key) => (float) $input[$key], ['west', 'south', 'east', 'north']);
        [$west, $south, $east, $north] = $bbox;
        abort_if($east <= $west || $north <= $south || ($east - $west) * ($north - $south) > 0.08, 422);
        $table = $data->table('infrastructure', 'osm_buildings');
        abort_unless($data->exists($table), 404);
        $key = 'geoveil-buildings:'.implode(':', array_map(fn ($value) => number_format($value, 4, '.', ''), $bbox));
        return response()->json(Cache::remember($key, 60, function () use ($table, $bbox, $west, $south, $east, $north) {
            $query = DB::table($table)->select(['id', 'osm_id', 'osm_type', 'name', 'building', 'height', 'building_levels', 'render_height', 'render_min_height', 'height_source'])->limit(10000);
            if (DB::connection()->getDriverName() === 'pgsql') {
                $query->selectRaw('ST_AsGeoJSON(geometry) AS geometry')
                    ->whereRaw('geometry && ST_MakeEnvelope(?, ?, ?, ?, 4326)', $bbox)
                    ->whereRaw('ST_Intersects(geometry, ST_MakeEnvelope(?, ?, ?, ?, 4326))', $bbox);
            } else $query->addSelect('geometry');
            $features = [];
            foreach ($query->get() as $row) {
                $geometry = is_string($row->geometry) ? json_decode($row->geometry, true) : $row->geometry;
                if (!in_array($geometry['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) continue;
                if (DB::connection()->getDriverName() !== 'pgsql' && !self::intersects($geometry['coordinates'], $bbox)) continue;
                $features[] = ['type' => 'Feature', 'id' => $row->id, 'geometry' => $geometry,
                    'properties' => ['name' => $row->name, 'building' => $row->building, 'osm_id' => $row->osm_id,
                        'osm_type' => $row->osm_type, 'height_source' => $row->height_source,
                        'height' => $row->height, 'building_levels' => $row->building_levels,
                        'render_height' => (float) $row->render_height, 'render_min_height' => (float) $row->render_min_height]];
            }
            return ['type' => 'FeatureCollection', 'features' => $features];
        }));
    }

    private static function intersects(array $coordinates, array $bbox): bool
    {
        if (is_numeric($coordinates[0] ?? null)) return $coordinates[0] >= $bbox[0] && $coordinates[0] <= $bbox[2]
            && $coordinates[1] >= $bbox[1] && $coordinates[1] <= $bbox[3];
        foreach ($coordinates as $part) if (self::intersects($part, $bbox)) return true;
        return false;
    }
}
