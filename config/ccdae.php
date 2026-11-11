<?php
return [
    // The master identity is shared across the new modules. A blank value disables writes.
    'master_email' => env('CCDAE_MASTER_EMAIL', 'master@ccdae.local'),
    'original_cache_prefix' => env('CCDAE_ORIGINAL_CACHE_PREFIX', 'laravel-cache-'),
];
