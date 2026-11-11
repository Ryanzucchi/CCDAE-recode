<?php

namespace App\Services;

use GeoVeil\Forms\{Field, Form};
use GeoVeil\Resources\Resource;
use Illuminate\Support\Facades\DB;

class TrafficRecordResource
{
    public function get(?object $record = null): Resource
    {
        $selected = [];
        if ($record?->via_transito_id) $selected[$record->via_transito_id] = DB::table((new MapData)->table('mobility', 'via_transitos'))->where('id', $record->via_transito_id)->value('nome') ?? 'Via #'.$record->via_transito_id;
        $form = Form::make('registro-transito')->section('Leitura da via', [
            Field::remoteSelect('via_transito_id', route('resource.lookup', 'vias'), $selected)->label('Via de trânsito')->required(),
            Field::datetime('timestamp')->label('Data e hora')->required()->step('1')->default(now()->format('Y-m-d\TH:i:s')),
            Field::number('veiculos_total')->label('Total de veículos')->step('1'),
            Field::number('velocidade_media')->label('Velocidade média (km/h)')->step('0.01'),
            Field::number('altura_media_veiculos')->label('Altura média (m)')->step('0.01'),
            Field::number('altura_maxima_veiculos')->label('Altura máxima (m)')->step('0.01'),
            Field::number('indice_congestionamento')->label('Índice de congestionamento')->step('1'),
            Field::text('nivel_servico')->label('Nível de serviço'),
        ]);
        return Resource::make('registros-transito', 'Registros de trânsito', 'mobility.registro_transitos')->form($form)
            ->columns(['via_nome' => 'Via', 'timestamp' => 'Data e hora', 'veiculos_total' => 'Veículos', 'velocidade_media' => 'Velocidade média', 'altura_media_veiculos' => 'Altura média', 'altura_maxima_veiculos' => 'Altura máxima', 'nivel_servico' => 'Nível de serviço']);
    }
}
