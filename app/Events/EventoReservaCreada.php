<?php

namespace App\Events;

class EventoReservaCreada extends EventoBaseMaquina
{
    public function broadcastAs(): string
    {
        return 'reserva.creada';
    }
}
