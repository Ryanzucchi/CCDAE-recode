<?php

namespace App\Services;

use App\Models\Distrito;
use GeoVeil\Forms\{Field, Form};
use GeoVeil\Resources\Resource;

class RoadResource
{
    public const TYPES = ['trunk' => 'Rodovia / via expressa', 'primary' => 'Via primária', 'secondary' => 'Via secundária', 'tertiary' => 'Via terciária', 'residential' => 'Via residencial'];
    public const DIRECTIONS = ['inicio_fim' => 'Início → fim da geometria', 'fim_inicio' => 'Fim → início da geometria', 'ambos' => 'Ambos os sentidos'];
    public const PAVEMENTS = ['bom' => 'Bom', 'regular' => 'Regular', 'ruim' => 'Ruim', 'asfalto' => 'Asfalto', 'concreto' => 'Concreto', 'paver' => 'Paver / paralelepípedo', 'cascalho' => 'Cascalho', 'terra' => 'Terra', 'desconhecido' => 'Desconhecido'];

    public function get(?object $road = null): Resource
    {
        $districts = Distrito::query()->orderBy('nome')->pluck('nome', 'id')->all();
        $types = self::TYPES;
        if ($road?->tipo && !isset($types[$road->tipo])) $types[$road->tipo] = $road->tipo.' (valor existente)';
        $pavements = self::PAVEMENTS;
        if ($road?->estado_pavimento && !isset($pavements[$road->estado_pavimento])) $pavements[$road->estado_pavimento] = $road->estado_pavimento.' (valor existente)';
        $form = Form::make('via-transito')
            ->section('Dados da via', [
                Field::select('distrito_id', $districts)->label('Distrito'),
                Field::text('nome')->label('Nome'),
                Field::text('osm_way_id')->label('ID da via no OpenStreetMap')->disabled(),
                Field::select('tipo', $types)->label('Tipo'),
                Field::number('numero_faixas')->label('Número de faixas')->step('1')->min(1),
                Field::select('sentido', ['unica' => 'Mão única', 'dupla' => 'Mão dupla'])->label('Sentido'),
                Field::select('direcao_fluxo', self::DIRECTIONS)->label('Direção do fluxo')->required()->default('ambos'),
                Field::number('limite_velocidade')->label('Limite de velocidade (km/h)')->step('1')->min(1),
                Field::select('estado_pavimento', $pavements)->label('Pavimento / conservação'),
                Field::number('inclinacao_pista')->label('Inclinação da pista (%)')->step('0.01'),
            ])->section('Fluxo', [
                Field::text('nivel_congestionamento')->label('Nível de congestionamento')->required()->default('livre'),
                Field::number('velocidade_media')->label('Velocidade média (km/h)')->step('1'),
                Field::number('volume_veiculos')->label('Volume de veículos')->step('1'),
                Field::select('impacto_manutencao', ['baixo' => 'Baixo', 'medio' => 'Médio', 'alto' => 'Alto'])->label('Impacto de manutenção')->required()->default('baixo'),
                Field::datetime('ultima_atualizacao')->label('Última atualização')->required()->default(now()->format('Y-m-d\TH:i')),
            ])->section('Traçado da via', [Field::line()->label('Geometria da via')]);
        return Resource::make('vias', 'Vias de trânsito', 'mobility.via_transitos')->form($form)
            ->columns(['distrito_id' => 'Distrito', 'nome' => 'Nome', 'tipo' => 'Tipo', 'numero_faixas' => 'Faixas', 'sentido' => 'Sentido', 'direcao_fluxo' => 'Direção do fluxo', 'limite_velocidade' => 'Limite km/h', 'estado_pavimento' => 'Pavimento', 'inclinacao_pista' => 'Inclinação %', 'nivel_congestionamento' => 'Congestionamento', 'velocidade_media' => 'Velocidade média', 'volume_veiculos' => 'Volume de veículos', 'impacto_manutencao' => 'Impacto de manutenção', 'ultima_atualizacao' => 'Última atualização', 'created_at' => 'Criado em', 'updated_at' => 'Atualizado em']);
    }
}
