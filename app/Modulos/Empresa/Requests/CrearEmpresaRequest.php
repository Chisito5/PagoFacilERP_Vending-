<?php

namespace App\Modulos\Empresa\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'CodigoEmpresa' => ['required', 'string', 'max:50'],
            'RazonSocial' => ['required', 'string', 'max:255'],
            'NombreComercial' => ['nullable', 'string', 'max:255'],
            'Nit' => ['nullable', 'string', 'max:50'],
            'Telefono' => ['nullable', 'string', 'max:50'],
            'Correo' => ['nullable', 'email', 'max:150'],
            'DireccionFiscal' => ['nullable', 'string', 'max:255'],

            'IdTipoEmpresa' => ['required', 'integer'],
            'IdEstado' => ['required', 'integer'],

            // Auditoría PagoFacil
            'Usr' => ['required', 'string', 'max:50'],
        ];
    }
}