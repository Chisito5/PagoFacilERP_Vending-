<?php

namespace App\Events;

class EventoVentaCreada extends EventoBaseMaquina
{
    public function broadcastAs(): string
    {
        return 'venta.creada';
    }
}
