<?php

namespace App\Services;

use App\Models\Distrito;
use Illuminate\Support\Facades\DB;
use GeoVeil\Forms\{Field, Form};
use GeoVeil\Resources\Resource;

class InfrastructureResourceCatalog
{//compelxidade e caos total
    public const ORIGINAL_SLUGS = [
        'postes' => 'postes', 'antenas' => 'antenas',
        'equipamentos_infraestrutura' => 'equipamento-infraestruturas',
        'centrais_distribuicao' => 'central-distribuicaos', 'cabeamentos' => 'cabeamentos',
    ];
    //talvez devesse guardar isso no banco e fazer um relation querry na mdel e chamar aqui
    public const CONSERVATION = ['novo' => 'Novo', 'bom' => 'Bom', 'regular' => 'Regular', 'ruim' => 'Ruim', 'critico' => 'Crítico'];
    public const MATERIALS = ['concreto' => 'Concreto', 'madeira' => 'Madeira', 'ferro' => 'Ferro', 'fibra' => 'Fibra'];
    public const CABLE_TYPES = ['fibra_optica' => 'Fibra Óptica', 'fibra_backbone' => 'Fibra Backbone', 'cobre_telefonia' => 'Cobre (Telefonia)', 'eletrico_alta_tensao' => 'Elétrico (Alta Tensão)', 'eletrico_baixa_tensao' => 'Elétrico (Baixa Tensão)'];
    public const SIGNALS = ['5G' => '5G', '4G' => '4G', '3G' => '3G', 'Radio' => 'Rádio / Micro-ondas', 'Satélite' => 'Satélite'];

    public function tableColumns(string $kind): array
    {
        return match ($kind) {//se ta buscando no banco esse colunas seria interessante so trazer o 'postes' sem precisar citra tudo isso, o feafault abort por padrao mesmo sem ser ffigitado, modulatrizar essa parte e essencial
            'postes' => ['distrito_id' => 'Distrito', 'codigo_patrimonio' => 'Patrimônio', 'latitude' => 'Latitude', 'longitude' => 'Longitude', 'material' => 'Material', 'altura_metros' => 'Altura (m)', 'resistencia_kg' => 'Resistência (kg)', 'possui_iluminacao' => 'Iluminação', 'data_instalacao' => 'Instalação', 'ultima_manutencao' => 'Última manutenção', 'estado_conservacao' => 'Conservação'],
            'antenas' => ['distrito_id' => 'Distrito', 'codigo_patrimonio' => 'Patrimônio', 'latitude' => 'Latitude', 'longitude' => 'Longitude', 'tipo_sinal' => 'Sinal', 'frequencia_mhz' => 'Frequência (MHz)', 'alcance_metros' => 'Alcance (m)', 'potencia_dbm' => 'Potência (dBm)', 'proprietario' => 'Proprietário', 'data_instalacao' => 'Instalação', 'ultima_manutencao' => 'Última manutenção', 'estado_conservacao' => 'Conservação'],
            'equipamentos_infraestrutura' => ['distrito_id' => 'Distrito', 'poste_id' => 'Poste', 'central_id' => 'Central', 'nome' => 'Nome', 'codigo_patrimonio' => 'Patrimônio', 'tipo' => 'Tipo', 'modelo' => 'Modelo', 'numero_serie' => 'Número de série', 'latitude' => 'Latitude', 'longitude' => 'Longitude', 'data_instalacao' => 'Instalação', 'ultima_manutencao' => 'Última manutenção', 'estado_conservacao' => 'Conservação'],
            'centrais_distribuicao' => ['distrito_id' => 'Distrito', 'nome' => 'Nome', 'codigo_patrimonio' => 'Patrimônio', 'latitude' => 'Latitude', 'longitude' => 'Longitude', 'tipo' => 'Tipo', 'capacidade' => 'Capacidade', 'area_m2' => 'Área (m²)', 'data_instalacao' => 'Instalação', 'ultima_manutencao' => 'Última manutenção', 'estado_conservacao' => 'Conservação'],
            'cabeamentos' => ['distrito_id' => 'Distrito', 'nome' => 'Nome', 'codigo_patrimonio' => 'Patrimônio', 'tipo_cabo' => 'Tipo de cabo', 'capacidade' => 'Capacidade', 'revestimento' => 'Revestimento', 'subterraneo' => 'Subterrâneo', 'extensao_metros' => 'Extensão (m)', 'poste_origem_id' => 'Poste de origem', 'poste_destino_id' => 'Poste de destino', 'central_origem_id' => 'Central de origem', 'central_destino_id' => 'Central de destino', 'data_instalacao' => 'Instalação', 'ultima_manutencao' => 'Última manutenção', 'estado_conservacao' => 'Conservação'],
            default => abort(404),
        };
    }

    public function sortable(string $kind): array
    {
        $common = ['distrito_id', 'data_instalacao', 'ultima_manutencao', 'created_at', 'updated_at'];
        return match ($kind) {
            'postes' => [...$common, 'latitude', 'longitude', 'altura_metros', 'resistencia_kg'],
            'antenas' => [...$common, 'latitude', 'longitude', 'frequencia_mhz', 'alcance_metros', 'potencia_dbm'],
            'equipamentos_infraestrutura' => [...$common, 'poste_id', 'central_id', 'latitude', 'longitude'],
            'centrais_distribuicao' => [...$common, 'latitude', 'longitude', 'area_m2'],
            'cabeamentos' => [...$common, 'extensao_metros', 'poste_origem_id', 'poste_destino_id', 'central_origem_id', 'central_destino_id'],
            default => abort(404),
        };
    }

    public function searchable(string $kind): array
    {
        return match ($kind) {
            'postes' => ['codigo_patrimonio', 'material', 'estado_conservacao'],
            'antenas' => ['codigo_patrimonio', 'tipo_sinal', 'proprietario', 'estado_conservacao'],
            'equipamentos_infraestrutura' => ['nome', 'codigo_patrimonio', 'tipo', 'modelo', 'numero_serie', 'estado_conservacao'],
            'centrais_distribuicao' => ['nome', 'codigo_patrimonio', 'tipo', 'capacidade', 'estado_conservacao'],
            'cabeamentos' => ['nome', 'codigo_patrimonio', 'tipo_cabo', 'capacidade', 'revestimento', 'estado_conservacao'],
            default => abort(404),
        };
    }

    public function get(string $kind, ?object $selected = null): Resource
    {// se ja e ŕa infraestrutra chammar 'postes', 'antenas' é reduntante alem de nao preisar do strict true ou do 404 eisso deveria ser padrao um exemplo ideal:
        //get(this($listainfestrutura isso pode vir na model como fun{cao de vbusca))
        abort_unless(in_array($kind, ['postes', 'antenas', 'equipamentos_infraestrutura', 'centrais_distribuicao', 'cabeamentos'], true), 404);
        //precisa dessa variavel um relacionamento na modle nao resovel ai s chama algo como ->nomedafuncaonamodel
        $districts = Distrito::query()->orderBy('nome')->pluck('nome', 'id')->all();
        //precisa dessa variavel um relacionamento na modle nao resovel ai s chama algo como ->nomedafuncaonamodel
        $main = [Field::select('distrito_id', $districts)->label('Distrito')->searchable(), Field::text('codigo_patrimonio')->label('Código de Patrimônio')];
        //precisa dessa variavel um relacionamento na modle nao resovel ai s chama algo como ->nomedafuncaonamodel
        $links = [];
        //simplficar sempre exemplo idealpara substitor trecho ate o proximo comentario:
        //agroup (listaitensinfra,4){[ da q quantidade de gruposcomeca cotnado do zero
        //field::select(0, 'material') o zero indica a qual kind aquiklo pertece no caso postes sendo 0, se nao colocar o 0 ele preenche com o numero nao utilizado seguente como padrao invisivel
        //->label('Material')
        //->required()],
        // ultimo quando eu colcoare
        //[
        // field::select('material') apos fechar com], o padrao sera 1 e nao mais zero sem precisar colcoar o numero
        //}
        if ($kind === 'postes') {
            $main = [...$main,
                Field::select('material', self::MATERIALS)->label('Material')->required()->default('concreto'),
                Field::number('altura_metros')->label('Altura (m)')->step('0.01'),
                Field::number('resistencia_kg')->label('Resistência (kg)')->step('1'),
                Field::toggle('possui_iluminacao')->label('Possui iluminação')];
            //fim
        } elseif ($kind === 'antenas') {
            $main = [...$main,
                Field::select('tipo_sinal', self::SIGNALS)->label('Tipo de Sinal'),
                Field::number('frequencia_mhz')->label('Frequência (MHz)')->step('0.01'),
                Field::number('alcance_metros')->label('Alcance (m)')->step('0.01'),
                Field::number('potencia_dbm')->label('Potência (dBm)')->step('0.01'),
                Field::text('proprietario')->label('Proprietário (Telecom)')];
        } elseif ($kind === 'equipamentos_infraestrutura') {
            $main = [Field::text('nome')->label('Nome do Equipamento')->required(),
                Field::text('codigo_patrimonio')->label('Código de Patrimônio'),
                Field::text('tipo')->label('Tipo (Ex: Transformador, Roteador)'),
                Field::text('modelo')->label('Modelo'), Field::text('numero_serie')->label('Número de Série')];
            $posts = $selected?->poste_id ? [$selected->poste_id => DB::table((new MapData)->table('infrastructure', 'postes'))->where('id', $selected->poste_id)->value('codigo_patrimonio') ?? 'Poste #'.$selected->poste_id] : [];
            $centrals = $selected?->central_id ? [$selected->central_id => DB::table((new MapData)->table('infrastructure', 'centrais_distribuicao'))->where('id', $selected->central_id)->value('nome') ?? 'Central #'.$selected->central_id] : [];
            $links = [Field::select('distrito_id', $districts)->label('Distrito')->searchable(),
                Field::remoteSelect('poste_id', route('resource.lookup', 'postes'), $posts)->label('Alocado no Poste (opcional)'),
                Field::remoteSelect('central_id', route('resource.lookup', 'centrais'), $centrals)->label('Alocado na Central (opcional)')];
        } elseif ($kind === 'centrais_distribuicao') {
            $types = ['Energia' => 'Energia Elétrica', 'Telecom' => 'Telecomunicações / Datacenter', 'Agua' => 'Saneamento / Água', 'Gas' => 'Gás Natural'];
            if ($selected?->tipo && !isset($types[$selected->tipo])) $types[$selected->tipo] = $selected->tipo;
            $main = [Field::text('nome')->label('Nome da Central')->required(),
                Field::select('distrito_id', $districts)->label('Distrito')->searchable(),
                Field::text('codigo_patrimonio')->label('Código de Patrimônio'),
                Field::select('tipo', $types)->label('Tipo de Central'),
                Field::text('capacidade')->label('Capacidade (ex: 138kV, 10Gbps)'),
                Field::number('area_m2')->label('Área (m²)')->step('0.01')];
        } else {
            $posts = []; $centrals = [];
            foreach (['poste_origem_id', 'poste_destino_id'] as $field) if ($selected?->$field) $posts[$selected->$field] = DB::table((new MapData)->table('infrastructure', 'postes'))->where('id', $selected->$field)->value('codigo_patrimonio') ?? 'Poste #'.$selected->$field;
            foreach (['central_origem_id', 'central_destino_id'] as $field) if ($selected?->$field) $centrals[$selected->$field] = DB::table((new MapData)->table('infrastructure', 'centrais_distribuicao'))->where('id', $selected->$field)->value('nome') ?? 'Central #'.$selected->$field;
            $main = [Field::text('nome')->label('Identificação da Linha / Cabo'), Field::text('codigo_patrimonio')->label('Código de Patrimônio'),
                Field::select('tipo_cabo', self::CABLE_TYPES)->label('Tipo de Cabeamento'),
                Field::text('capacidade')->label('Capacidade (Ex: 144 FO, 13.8kV)'),
                Field::text('revestimento')->label('Revestimento'),
                Field::number('extensao_metros')->label('Extensão calculada (m)')->step('0.01'),
                Field::toggle('subterraneo')->label('Cabeamento subterrâneo')];
            $links = [Field::remoteSelect('poste_origem_id', route('resource.lookup', 'postes'), $posts)->label('Poste de origem'), Field::remoteSelect('poste_destino_id', route('resource.lookup', 'postes'), $posts)->label('Poste de destino'),
                Field::remoteSelect('central_origem_id', route('resource.lookup', 'centrais'), $centrals)->label('Central de origem'), Field::remoteSelect('central_destino_id', route('resource.lookup', 'centrais'), $centrals)->label('Central de destino'),
                Field::select('distrito_id', $districts)->label('Distrito predominante')->searchable()];
        }
        $mainTitle = $kind === 'cabeamentos' ? 'Dados Principais (Aresta do Grafo)' : 'Dados Principais';
        $locationTitle = match ($kind) {
            'postes' => 'Localização Espacial (Nó do Grafo)',
            'equipamentos_infraestrutura' => 'Localização Física (Caso não vinculado a Poste/Central)',
            'centrais_distribuicao' => 'Fronteiras e Localização (Nó do Grafo)',
            'cabeamentos' => 'Traçado Espacial (Edge)',
            default => 'Localização Espacial',
        };
        $form = Form::make($kind)->section($mainTitle, $main, null, $kind === 'equipamentos_infraestrutura' ? 3 : 2);
        if ($links && $kind !== 'cabeamentos') $form->section('Vínculos Espaciais', $links, null, 3);
        $form->section('Desgaste & Vida Útil', [
                Field::select('estado_conservacao', self::CONSERVATION)->label('Estado de Conservação')->required()->default('bom'),
                Field::date('data_instalacao')->label('Instalação'),
                Field::date('ultima_manutencao')->label('Última Manutenção'),
            ], null, 3);
        if ($kind === 'cabeamentos') $form->section('Roteamento do Grafo (Conexões Físicas)', $links);
        $form->section($locationTitle, [$kind === 'centrais_distribuicao' ? Field::geometry()->label('Polígono ou ponto da central') : ($kind === 'cabeamentos' ? Field::line()->label('Traçado do cabeamento') : Field::point()->label($kind === 'postes' ? 'Posição exata do poste' : ($kind === 'antenas' ? 'Posição da antena' : 'Posição do equipamento')))], null, 1)
            ->section('Observações', [Field::textarea('observacoes')->label('Observações Adicionais')], null, 1);
        return Resource::make($kind, $kind === 'postes' ? 'Postes' : ($kind === 'antenas' ? 'Antenas' : ($kind === 'equipamentos_infraestrutura' ? 'Equipamentos' : ($kind === 'centrais_distribuicao' ? 'Centrais de distribuição' : 'Cabeamentos'))), 'infrastructure.'.$kind)
            ->form($form)->columns($this->tableColumns($kind))
            ->optionalColumns(['created_at' => 'Criado em', 'updated_at' => 'Atualizado em']);
    }
}
