<?php

namespace App\Events;

class EventoReservaCancelada extends EventoBaseMaquina
{
    public function broadcastAs(): string
    {
        return 'reserva.cancelada';
    }
}
