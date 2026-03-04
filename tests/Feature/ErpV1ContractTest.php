<?php

namespace Tests\Feature;

use Tests\TestCase;

class ErpV1ContractTest extends TestCase
{
    public function test_erp_v1_responde_503_si_flag_deshabilitado(): void
    {
        config()->set('erp.v1_habilitado', false);

        $toResponse = $this->postJson('/api/erp/v1/auth/login', []);
        $toResponse->assertStatus(503);
        $toResponse->assertJsonStructure([
            'Ok',
            'Mensaje',
            'Datos',
            'Errores',
            'Meta',
        ]);
    }

    public function test_erp_v1_respuesta_401_mantiene_contrato(): void
    {
        config()->set('erp.v1_habilitado', true);

        $toResponse = $this->getJson('/api/erp/v1/transacciones');
        $toResponse->assertStatus(401);
        $toResponse->assertJsonStructure([
            'Ok',
            'Mensaje',
            'Datos',
            'Errores',
            'Meta',
        ]);
    }

    public function test_openapi_erp_v1_existe(): void
    {
        $this->assertFileExists(base_path('docs/openapi/erp-v1.yaml'));
    }
}
