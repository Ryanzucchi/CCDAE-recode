<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use LogicException;

/**
 * Contrato de fila compatível com o job executado pelo worker do CCDAE original.
 * O recode só enfileira este objeto; a implementação da coleta vive no worker original.
 */
class CollectClimateData implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        throw new LogicException('A coleta climática deve ser executada pelo worker do CCDAE original.');
    }
}
