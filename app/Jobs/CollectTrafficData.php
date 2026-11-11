<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use LogicException;

/** Contrato de fila compatível com o job executado pelo worker do CCDAE original. */
class CollectTrafficData implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 300;
    public int $tries = 2;
    public int $uniqueFor = 2400;

    public function uniqueId(): string
    {
        return 'tomtom-vector-flow-city-coverage';
    }

    public function handle(): void
    {
        throw new LogicException('A coleta de trânsito deve ser executada pelo worker do CCDAE original.');
    }
}
