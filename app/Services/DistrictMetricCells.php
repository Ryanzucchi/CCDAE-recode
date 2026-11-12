<?php

namespace App\Services;

use App\Models\Distrito;
use GeoVeil\Geometry\PolygonSampler;

class DistrictMetricCells
{
    public function cells(string $type, ?int $districtId = null): array
    {
        $sampler = new PolygonSampler;
        $cells = [];
        foreach (Distrito::query()->when($districtId, fn ($q) => $q->where('id', $districtId))->whereNotNull('geojson')->get() as $district) {
            $population = (float) ($district->populacao ?? 0);
            $value = match ($type) {
                'consumo' => $district->consumo_medio_kwh !== null ? (float) $district->consumo_medio_kwh : $population * 0.18,
                'demanda' => round($population * 0.78),
                default => $population,
            };//isso ta muito texto para fazer pouca coisa
            foreach ($sampler->sample($district->geojson, (float) $district->longitude, (float) $district->latitude) as [$lng, $lat]) {
                if (!is_finite($lng) || !is_finite($lat) || abs($lat) > 90 || abs($lng) > 180) continue;
                $cells[] = ['lng' => $lng, 'lat' => $lat, 'count' => 1, 'value' => $value, 'name' => $district->nome];
            }//tambem muito texto
        }
        return $cells;
    }
}
