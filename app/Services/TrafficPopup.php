<?php

namespace App\Services;

class TrafficPopup
{
    public const FIELDS = [
        'popup_nome' => 'Via',
        'popup_congestionamento' => 'Congestionamento',
        'popup_velocidade' => 'Velocidade média',
        'popup_direcao' => 'Direção do fluxo',
        'popup_impacto' => 'Impacto logístico',
        'popup_volume' => 'Volume na hora',
        'popup_ocorrencias' => 'Ocorrências',
        'popup_origem' => 'Origem do dado',
    ];

    public function decorate(object $road): object
    {
        $road->popup_nome = 'Via: '.($road->nome ?: 'Via');
        $road->popup_congestionamento = mb_strtoupper((string) ($road->nivel_congestionamento ?: 'livre'));
        $road->popup_velocidade = $road->velocidade_media ? $road->velocidade_media.' km/h' : 'Não informada';
        $road->popup_direcao = match ($road->direcao_fluxo ?? null) {
            'inicio_fim' => 'Início → fim da via',
            'fim_inicio' => 'Fim → início da via',
            default => 'Ambos os sentidos',
        };
        $road->popup_impacto = mb_strtoupper((string) ($road->impacto_manutencao ?: 'não informado'));
        $road->popup_volume = isset($road->veiculos_total) ? $road->veiculos_total.' veículos' : 'Não informado';
        $road->popup_ocorrencias = (int) ($road->acidentes_ativos ?? 0).' acidente(s), '.(int) ($road->obras_ativas ?? 0).' obra(s)'.(! empty($road->alagamento_ativo) ? ', alagamento' : '');
        $road->popup_origem = match ($road->origem_dado ?? null) {
            'real' => 'Real — '.($road->fonte_dado ?: 'fonte externa'),
            'misto' => 'Misto — '.($road->fonte_dado ?: 'fonte real e estimativas'),
            default => 'Simulado — fallback CCDAE',
        };
        return $road;
    }
}
