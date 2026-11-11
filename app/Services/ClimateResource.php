<?php

namespace App\Services;

use GeoVeil\Resources\Resource;

class ClimateResource
{
    public const ORIGINAL_SLUGS = [
        'chuva' => 'chuva-registradas', 'uv' => 'indice-u-v-s',
        'particulas' => 'particula-ars', 'pressao' => 'pressao-atmosfericas',
        'radiacao' => 'radiacao-solars', 'temperatura' => 'temperatura-registradas',
        'vento' => 'vento-registrados',
    ];
    public const TYPES = [
        'temperatura' => ['Temperaturas', 'temperatura_registrada', ['temperatura' => 'Temp (°C)']],
        'vento' => ['Ventos', 'vento_registrado', ['velocidade' => 'Velocidade (km/h)', 'direcao' => 'Direção (°)']],
        'chuva' => ['Chuvas', 'chuva_registrada', ['precipitacao_mm' => 'Precipitação (mm)']],
        'pressao' => ['Pressão atmosférica', 'pressao_atmosferica', ['pressao_hpa' => 'Pressão (hPa)']],
        'radiacao' => ['Radiação solar', 'radiacao_solar', ['radiacao_w_m2' => 'Radiação (W/m²)']],
        'uv' => ['Índice UV', 'indice_uv', ['uv' => 'Índice UV']],
        'particulas' => ['Partículas do ar', 'particulas_ar', ['pm25' => 'PM2.5 (µg/m³)', 'pm10' => 'PM10 (µg/m³)', 'poeira' => 'Poeira (µg/m³)', 'areia' => 'Areia (µg/m³)', 'poluentes' => 'Ozônio (µg/m³)']],
    ];

    public function get(string $kind): Resource
    {
        abort_unless(isset(self::TYPES[$kind]), 404);
        [$title, $table, $columns] = self::TYPES[$kind];
        return Resource::make($kind, $title, 'climate.'.$table)
            ->columns(['distrito_nome' => 'Distrito', 'timestamp' => 'Data/Hora'] + $columns);
    }

    public function table(string $kind): string
    {
        abort_unless(isset(self::TYPES[$kind]), 404);
        return self::TYPES[$kind][1];
    }
}
