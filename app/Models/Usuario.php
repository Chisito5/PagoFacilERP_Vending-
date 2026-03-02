<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable
{
    protected $connection = 'mysqlNegocio';

    protected $table = 'USUARIO';

    protected $primaryKey = 'Usuario';

    public $timestamps = false;

    protected $fillable = [
        'Empresa',
        'NombreUsuario',
        'ClaveCifrada',
        'Nombres',
        'Apellidos',
        'Correo',
        'Telefono',
        'DocumentoIdentidad',
        'Estado',
        'Usr',
        'UsrFecha',
        'UsrHora',
    ];

    protected $hidden = [
        'ClaveCifrada',
    ];

    public function getAuthPassword(): string
    {
        return (string)$this->ClaveCifrada;
    }
}
