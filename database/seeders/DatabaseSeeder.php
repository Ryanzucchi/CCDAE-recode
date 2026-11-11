<?php

namespace Database\Seeders;

use App\Models\Distrito;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            throw new LogicException('Os seeders do recode nao podem alterar o PostgreSQL compartilhado do CCDAE.');
        }

        $districts = [
            ['Centro', 32100, 5.20, -57.700, -16.070, -57.680, -16.050],
            ['Cavalhada', 24700, 7.80, -57.680, -16.070, -57.655, -16.050],
            ['Cidade Alta', 18300, 6.40, -57.700, -16.092, -57.680, -16.070],
            ['Jardim Uniao', 9800, 8.10, -57.680, -16.092, -57.655, -16.070],
        ];

        foreach ($districts as [$nome, $populacao, $area, $west, $south, $east, $north]) {
            Distrito::query()->updateOrCreate(
                ['cidade' => 'Caceres', 'nome' => $nome],
                [
                    'populacao' => $populacao,
                    'area_km2' => $area,
                    'geojson' => [
                        'type' => 'Polygon',
                        'coordinates' => [[
                            [$west, $south],
                            [$east, $south],
                            [$east, $north],
                            [$west, $north],
                            [$west, $south],
                        ]],
                    ],
                ],
            );
        }
    }
}
