<?php

return [
    'terrain' => [
        'enabled' => (bool) env('MAP_TERRAIN_ENABLED', true),
        'terrarium_tile_url' => env('MAP_TERRAIN_TILE_URL', 'https://s3.amazonaws.com/elevation-tiles-prod/terrarium/{z}/{x}/{y}.png'),
        'zoom' => (int) env('MAP_TERRAIN_ZOOM', 12),
        'exaggeration' => (float) env('MAP_TERRAIN_EXAGGERATION', 1.35),
        'attribution' => env('MAP_TERRAIN_ATTRIBUTION', 'Mapzen Terrain Tiles / AWS Open Data'),
    ],
];
