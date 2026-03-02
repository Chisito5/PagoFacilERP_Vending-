<?php

namespace App\Soporte;

class ControlVersionService
{
    public function versionDesdeFila(object|array $tmFila): string
    {
        $tcFecha = is_array($tmFila) ? (string)($tmFila['UsrFecha'] ?? '') : (string)($tmFila->UsrFecha ?? '');
        $tcHora = is_array($tmFila) ? (string)($tmFila['UsrHora'] ?? '') : (string)($tmFila->UsrHora ?? '');

        return trim($tcFecha . ' ' . $tcHora);
    }

    public function coincide(string $tcVersionEnviada, object|array $tmFila): bool
    {
        return trim($tcVersionEnviada) === $this->versionDesdeFila($tmFila);
    }
}
