<?php

namespace App\Events;

class EventoReposicionCreada extends EventoBaseMaquina
{
    public function broadcastAs(): string
    {
        return 'reposicion.creada';
    }
}
