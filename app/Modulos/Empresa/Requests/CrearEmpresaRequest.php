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
            'CodigoEmpresa' => ['required', 'string', 'max:30'],
            'RazonSocial' => ['required', 'string', 'max:150'],
            'NombreComercial' => ['nullable', 'string', 'max:150'],
            'Nit' => ['nullable', 'string', 'max:30'],
            'Telefono' => ['nullable', 'string', 'max:30'],
            'Correo' => ['nullable', 'email', 'max:120'],
            'DireccionFiscal' => ['nullable', 'string', 'max:255'],

            'TipoEmpresa' => ['required', 'integer'],
            'Estado' => ['required', 'integer'],

            'PlantillaVisualPredeterminada' => ['nullable', 'integer'],

            // Auditoría
            'Usr' => ['nullable', 'integer'],
        ];
    }
}
