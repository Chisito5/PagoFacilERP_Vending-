<?php

namespace App\Events;

class EventoReservaConfirmada extends EventoBaseMaquina
{
    public function broadcastAs(): string
    {
        return 'reserva.confirmada';
    }
}
