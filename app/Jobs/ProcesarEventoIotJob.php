<?php

namespace App\Jobs;

use App\Modulos\IntegracionIot\Services\IntegracionIotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcesarEventoIotJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 90;

    /** @var array<int,int> */
    public array $backoff = [10, 30, 60, 120];

    public function __construct(private int $pnEvento)
    {
    }

    public function handle(IntegracionIotService $toService): void
    {
        $toService->procesarEvento($this->pnEvento);
    }
}
