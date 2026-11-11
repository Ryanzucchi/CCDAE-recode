<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Distrito extends Model
{
    use HasFactory;

    protected $table = 'distritos';

    protected $fillable = [
        'nome',
        'cidade',
        'latitude',
        'longitude',
        'populacao',
        'populacao_fonte',
        'populacao_atualizada_em',
        'consumo_medio_kwh',
        'geojson',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'populacao' => 'integer',
            'populacao_atualizada_em' => 'datetime',
            'consumo_medio_kwh' => 'decimal:2',
            'geojson' => 'array',
        ];
    }

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'geo.distritos'
            : parent::getTable();
    }
}
