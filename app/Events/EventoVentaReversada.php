<?php

namespace App\Events;

class EventoVentaReversada extends EventoBaseMaquina
{
    public function broadcastAs(): string
    {
        return 'venta.reversada';
    }
}
