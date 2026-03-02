<?php

namespace App\Events;

class EventoStockActualizado extends EventoBaseMaquina
{
    public function broadcastAs(): string
    {
        return 'stock.actualizado';
    }
}
