<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class EventoBaseMaquina implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public bool $afterCommit = false;

    /**
     * @param array<string,mixed> $laDatos
     */
    public function __construct(
        public int $tnMaquina,
        public ?int $tnEmpresa = null,
        public array $laDatos = []
    ) {
    }

    /**
     * @return array<int,PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $laCanales = [new PrivateChannel('maquina.' . $this->tnMaquina)];

        if (($this->tnEmpresa ?? 0) > 0) {
            $laCanales[] = new PrivateChannel('empresa.' . $this->tnEmpresa);
        }

        return $laCanales;
    }

    /**
     * @return array<string,mixed>
     */
    public function broadcastWith(): array
    {
        return array_merge([
            'Maquina' => $this->tnMaquina,
            'Empresa' => $this->tnEmpresa,
            'FechaHora' => now()->toDateTimeString(),
        ], $this->laDatos);
    }
}
