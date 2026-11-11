<?php

return [
    // Configure an endpoint compatible with Nominatim; no public provider is enabled implicitly.
    'geocoder_endpoint' => env('GEOVEIL_GEOCODER_ENDPOINT'),
    'geocoder_user_agent' => env('GEOVEIL_GEOCODER_USER_AGENT', 'CCDAE-GeoVeil2/2.0'),
    'martin_url' => env('GEOVEIL_MARTIN_URL', 'http://martin:3000'),
    'vector_sources' => ['vias', 'postes', 'antenas', 'equipamentos', 'centrais', 'cabeamentos', 'energia_postes', 'energia_equipamentos', 'energia_centrais', 'energia_cabeamentos', 'energia_postes_3d', 'energia_equipamentos_3d', 'internet_antenas', 'internet_equipamentos', 'internet_cabeamentos', 'internet_antenas_3d'],
];
