<?php

namespace GeoVeil\Geocoding;

use GeoVeil\Contracts\Geocoder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

final class Nominatim implements Geocoder
{
    public function __construct(private string $userAgent, private string $endpoint) {}

    public function search(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 3 || mb_strlen($query) > 200) {
            throw new InvalidArgumentException('Informe entre 3 e 200 caracteres.');
        }
        $key = 'geoveil:geocode:'.hash('sha256', $this->endpoint.'|'.mb_strtolower($query));

        return Cache::remember($key, 86400, function () use ($query) {
            // Global upstream budget, including concurrent requests and different queries.
            if (! Cache::add('geoveil:geocoder-budget:'.hash('sha256', $this->endpoint), true, 2)) {
                throw new RuntimeException('Aguarde um instante antes de realizar outra busca.');
            }
            $response = Http::withUserAgent($this->userAgent)->acceptJson()->connectTimeout(3)->timeout(8)
                ->get($this->endpoint, ['q' => $query, 'format' => 'jsonv2', 'limit' => 5])->throw()->json();
            if (! is_array($response)) {
                throw new RuntimeException('Resposta inválida do serviço de localização.');
            }
            $results = [];
            foreach ($response as $item) {
                if (! isset($item['display_name'], $item['lon'], $item['lat']) || ! is_numeric($item['lon']) || ! is_numeric($item['lat'])) {
                    continue;
                }
                $lng = (float) $item['lon'];
                $lat = (float) $item['lat'];
                if (! is_finite($lng) || ! is_finite($lat) || abs($lng) > 180 || abs($lat) > 90) {
                    continue;
                }
                $results[] = ['label' => (string) $item['display_name'], 'longitude' => $lng, 'latitude' => $lat];
            }

            return $results;
        });
    }
}
