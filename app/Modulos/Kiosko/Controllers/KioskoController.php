<?php

namespace App\Modulos\Kiosko\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Kiosko\Services\KioskoService;
use App\Modulos\Reserva\Services\ReservaService;
use App\Modulos\Venta\Services\VentaService;
use App\Support\IdempotenciaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class KioskoController extends Controller
{
    public function __construct(
        private KioskoService $toKioskoService,
        private VentaService $toVentaService,
        private ReservaService $toReservaService
    ) {
    }

    public function Catalogo(int $Maquina): JsonResponse
    {
        $la = $this->toKioskoService->CatalogoPorMaquina($Maquina);
        if (($la['Estado'] ?? '') === 'NO_ENCONTRADO') {
            return $this->respuestaContrato(
                false,
                'Maquina no encontrada',
                [],
                [['Codigo' => 'NEG_404', 'Campo' => 'Maquina', 'Detalle' => 'No existe la maquina solicitada']],
                [],
                404
            );
        }

        return $this->respuestaContrato(
            true,
            'Catalogo recuperado correctamente',
            $la['Datos'] ?? [],
            [],
            $la['Meta'] ?? ['Origen' => 'kiosko_unificado_v1']
        );
    }

    public function Venta(Request $toRequest): JsonResponse
    {
        return $this->ejecutarIdempotenteKiosko($toRequest, function () use ($toRequest) {
            $toError = $this->validarSolicitud($toRequest, [
                'Maquina' => ['required', 'integer', 'min:1'],
                'CodigoSeleccion' => ['required', 'string', 'max:10'],
                'Cantidad' => ['required', 'integer', 'min:1'],
            ]);
            if ($toError) {
                return $toError;
            }

            $toCore = $this->toVentaService->VenderPorSeleccion(
                (int)$toRequest->input('Maquina'),
                (string)$toRequest->input('CodigoSeleccion'),
                (int)$toRequest->input('Cantidad')
            );

            return $this->adaptarRespuestaCore($toCore, 'VENTA', $toRequest);
        });
    }

    public function ReversaVenta(Request $toRequest): JsonResponse
    {
        return $this->ejecutarIdempotenteKiosko($toRequest, function () use ($toRequest) {
            $toError = $this->validarSolicitud($toRequest, [
                'Venta' => ['required', 'integer', 'min:1'],
                'Motivo' => ['required', 'string', 'max:255'],
            ]);
            if ($toError) {
                return $toError;
            }

            $toCore = $this->toVentaService->Reversar(
                (int)$toRequest->input('Venta'),
                (string)$toRequest->input('Motivo')
            );

            return $this->adaptarRespuestaCore($toCore, 'REVERSA', $toRequest);
        });
    }

    public function CrearReserva(Request $toRequest): JsonResponse
    {
        return $this->ejecutarIdempotenteKiosko($toRequest, function () use ($toRequest) {
            $toError = $this->validarSolicitud($toRequest, [
                'Maquina' => ['required', 'integer', 'min:1'],
                'CodigoSeleccion' => ['required', 'string', 'max:10'],
                'Cantidad' => ['required', 'integer', 'min:1'],
                'ExpiraSegundos' => ['nullable', 'integer', 'min:30', 'max:3600'],
            ]);
            if ($toError) {
                return $toError;
            }

            $toCore = $this->toReservaService->Reservar(
                (int)$toRequest->input('Maquina'),
                (string)$toRequest->input('CodigoSeleccion'),
                (int)$toRequest->input('Cantidad'),
                (int)($toRequest->input('ExpiraSegundos') ?? 120)
            );

            return $this->adaptarRespuestaCore($toCore, 'RESERVA_CREAR', $toRequest);
        });
    }

    public function ConfirmarReserva(Request $toRequest): JsonResponse
    {
        return $this->ejecutarIdempotenteKiosko($toRequest, function () use ($toRequest) {
            $toError = $this->validarSolicitud($toRequest, [
                'Reserva' => ['nullable', 'integer', 'min:1', 'required_without:ReservaExterna'],
                'ReservaExterna' => ['nullable', 'string', 'max:80', 'required_without:Reserva'],
            ]);
            if ($toError) {
                return $toError;
            }

            $toCore = $this->toReservaService->Confirmar(
                $toRequest->filled('Reserva') ? (int)$toRequest->input('Reserva') : 0,
                $toRequest->filled('ReservaExterna') ? (string)$toRequest->input('ReservaExterna') : null
            );

            return $this->adaptarRespuestaCore($toCore, 'RESERVA_CONFIRMAR', $toRequest);
        });
    }

    public function CancelarReserva(Request $toRequest): JsonResponse
    {
        return $this->ejecutarIdempotenteKiosko($toRequest, function () use ($toRequest) {
            $toError = $this->validarSolicitud($toRequest, [
                'Reserva' => ['nullable', 'integer', 'min:1', 'required_without:ReservaExterna'],
                'ReservaExterna' => ['nullable', 'string', 'max:80', 'required_without:Reserva'],
                'Motivo' => ['nullable', 'string', 'max:255'],
            ]);
            if ($toError) {
                return $toError;
            }

            $toCore = $this->toReservaService->Cancelar(
                $toRequest->filled('Reserva') ? (int)$toRequest->input('Reserva') : 0,
                $toRequest->filled('ReservaExterna') ? (string)$toRequest->input('ReservaExterna') : null,
                $toRequest->filled('Motivo') ? (string)$toRequest->input('Motivo') : null
            );

            return $this->adaptarRespuestaCore($toCore, 'RESERVA_CANCELAR', $toRequest);
        });
    }

    private function validarSolicitud(Request $toRequest, array $laReglas): ?JsonResponse
    {
        $toValidador = Validator::make($toRequest->all(), $laReglas);
        if (!$toValidador->fails()) {
            return null;
        }

        $laErrores = [];
        foreach ($toValidador->errors()->toArray() as $tcCampo => $laDetalle) {
            foreach ((array)$laDetalle as $tcMsg) {
                $laErrores[] = [
                    'Codigo' => 'VAL_422',
                    'Campo' => (string)$tcCampo,
                    'Detalle' => (string)$tcMsg,
                ];
            }
        }

        return $this->respuestaContrato(false, 'Error de validacion', [], $laErrores, [], 422);
    }

    /**
     * @param callable():JsonResponse $tfOperacion
     */
    private function ejecutarIdempotenteKiosko(Request $toRequest, callable $tfOperacion): JsonResponse
    {
        /** @var IdempotenciaService $toIdempotencia */
        $toIdempotencia = app(IdempotenciaService::class);

        return DB::connection('mysqlNegocio')->transaction(function () use ($toRequest, $tfOperacion, $toIdempotencia) {
            $laInicio = $toIdempotencia->iniciar($toRequest);

            if ($laInicio['estado'] === IdempotenciaService::ESTADO_FALTA_CLAVE) {
                return $this->respuestaContrato(
                    false,
                    'Clave-Idempotencia es obligatoria',
                    [],
                    [['Codigo' => 'REQ_400', 'Campo' => 'Clave-Idempotencia', 'Detalle' => 'Debe enviar el header Clave-Idempotencia']],
                    [],
                    400
                );
            }

            if ($laInicio['estado'] === IdempotenciaService::ESTADO_CONFLICTO) {
                return $this->respuestaContrato(
                    false,
                    'Clave-Idempotencia ya fue usada con otro payload',
                    [],
                    [['Codigo' => 'BUS_409', 'Campo' => 'Clave-Idempotencia', 'Detalle' => 'No puede reutilizar la misma clave con cuerpo diferente']],
                    [],
                    409
                );
            }

            if ($laInicio['estado'] === IdempotenciaService::ESTADO_EN_PROCESO) {
                return $this->respuestaContrato(
                    false,
                    'Solicitud en proceso',
                    [],
                    [['Codigo' => 'BUS_409', 'Campo' => 'Clave-Idempotencia', 'Detalle' => 'Existe una solicitud en proceso con la misma clave']],
                    [],
                    409
                );
            }

            if ($laInicio['estado'] === IdempotenciaService::ESTADO_REPETICION) {
                $laReplay = $laInicio['respuesta'] ?? [];
                if (!is_array($laReplay)) {
                    $laReplay = [];
                }

                return response()->json(
                    [
                        'Ok' => array_key_exists('Ok', $laReplay) ? (bool)$laReplay['Ok'] : true,
                        'Mensaje' => isset($laReplay['Mensaje']) ? (string)$laReplay['Mensaje'] : 'Repeticion idempotente',
                        'Datos' => $laReplay['Datos'] ?? [],
                        'Errores' => is_array($laReplay['Errores'] ?? null) ? $laReplay['Errores'] : [],
                        'Meta' => is_array($laReplay['Meta'] ?? null) ? $laReplay['Meta'] : [],
                    ],
                    (int)($laInicio['codigo_respuesta'] ?? 200),
                    ['X-Repeticion-Idempotencia' => 'si']
                );
            }

            $toResponse = $tfOperacion();
            $laBody = $toResponse->getData(true);
            if (!is_array($laBody)) {
                $laBody = [
                    'Ok' => $toResponse->getStatusCode() < 400,
                    'Mensaje' => 'Operacion ejecutada',
                    'Datos' => [],
                    'Errores' => [],
                    'Meta' => [],
                ];
            }

            $toIdempotencia->finalizar($laInicio, $toResponse->getStatusCode(), $laBody);
            return $toResponse;
        });
    }

    private function adaptarRespuestaCore(JsonResponse $toCore, string $tcOperacion, Request $toRequest): JsonResponse
    {
        $tnStatus = $toCore->getStatusCode();
        $laBody = $toCore->getData(true);
        if (!is_array($laBody)) {
            $laBody = [];
        }

        $tcMensaje = isset($laBody['Mensaje']) ? (string)$laBody['Mensaje'] : ($tnStatus < 400 ? 'Operacion ejecutada correctamente' : 'No se pudo completar la operacion');
        $laHeaders = $this->extraerHeadersRepeticion($toCore);

        if ($tnStatus >= 400 || (isset($laBody['Ok']) && $laBody['Ok'] === false)) {
            $laErrores = $this->normalizarErrores($laBody, $tnStatus, $tcMensaje);
            return $this->respuestaContrato(false, $tcMensaje, [], $laErrores, [], $tnStatus, $laHeaders);
        }

        $laDatosCore = isset($laBody['Datos']) && is_array($laBody['Datos']) ? $laBody['Datos'] : [];
        $laDatos = $this->normalizarDatosExito($tcOperacion, $laDatosCore, $toRequest, $tcMensaje);

        return $this->respuestaContrato(true, $tcMensaje, $laDatos, [], [], $tnStatus, $laHeaders);
    }

    /**
     * @param array<string,mixed> $laBody
     * @return array<int,array{Codigo:string,Campo:?string,Detalle:string}>
     */
    private function normalizarErrores(array $laBody, int $tnStatus, string $tcMensaje): array
    {
        $laErrores = [];
        if (isset($laBody['Errores']) && is_array($laBody['Errores'])) {
            foreach ($laBody['Errores'] as $laErr) {
                if (!is_array($laErr)) {
                    continue;
                }
                $laErrores[] = [
                    'Codigo' => isset($laErr['Codigo']) ? (string)$laErr['Codigo'] : $this->codigoErrorPorStatus($tnStatus),
                    'Campo' => $laErr['Campo'] ?? null,
                    'Detalle' => isset($laErr['Detalle']) ? (string)$laErr['Detalle'] : $tcMensaje,
                ];
            }
        }

        if (count($laErrores) === 0) {
            $laErrores[] = [
                'Codigo' => $this->codigoErrorPorStatus($tnStatus),
                'Campo' => null,
                'Detalle' => $tcMensaje,
            ];
        }

        return $laErrores;
    }

    /**
     * @param array<string,mixed> $laDatosCore
     * @return array<string,mixed>
     */
    private function normalizarDatosExito(string $tcOperacion, array $laDatosCore, Request $toRequest, string $tcMensaje): array
    {
        if ($tcOperacion === 'VENTA') {
            return [
                'Venta' => isset($laDatosCore['Venta']) ? (int)$laDatosCore['Venta'] : 0,
                'Maquina' => isset($laDatosCore['Maquina']) ? (int)$laDatosCore['Maquina'] : (int)$toRequest->input('Maquina', 0),
                'Celda' => isset($laDatosCore['Celda']) ? (int)$laDatosCore['Celda'] : null,
                'CodigoSeleccion' => isset($laDatosCore['CodigoSeleccion']) ? (string)$laDatosCore['CodigoSeleccion'] : (string)$toRequest->input('CodigoSeleccion', ''),
                'Cantidad' => isset($laDatosCore['Cantidad']) ? (int)$laDatosCore['Cantidad'] : (int)$toRequest->input('Cantidad', 0),
                'PrecioUnitario' => isset($laDatosCore['PrecioUnitario']) ? (float)$laDatosCore['PrecioUnitario'] : null,
            ];
        }

        if ($tcOperacion === 'REVERSA') {
            return [
                'Venta' => isset($laDatosCore['Venta']) ? (int)$laDatosCore['Venta'] : (int)$toRequest->input('Venta', 0),
                'Estado' => 'REVERTIDA',
            ];
        }

        if ($tcOperacion === 'RESERVA_CREAR') {
            return [
                'Reserva' => isset($laDatosCore['Reserva']) ? (int)$laDatosCore['Reserva'] : 0,
                'ReservaExterna' => $laDatosCore['ReservaExterna'] ?? null,
                'Maquina' => isset($laDatosCore['Maquina']) ? (int)$laDatosCore['Maquina'] : (int)$toRequest->input('Maquina', 0),
                'Celda' => isset($laDatosCore['Celda']) ? (int)$laDatosCore['Celda'] : null,
                'CodigoSeleccion' => isset($laDatosCore['CodigoSeleccion']) ? (string)$laDatosCore['CodigoSeleccion'] : (string)$toRequest->input('CodigoSeleccion', ''),
                'Cantidad' => isset($laDatosCore['Cantidad']) ? (int)$laDatosCore['Cantidad'] : (int)$toRequest->input('Cantidad', 0),
                'ExpiraEn' => $laDatosCore['ExpiraEn'] ?? null,
            ];
        }

        if ($tcOperacion === 'RESERVA_CONFIRMAR') {
            $tnReserva = isset($laDatosCore['Reserva']) ? (int)$laDatosCore['Reserva'] : (int)$toRequest->input('Reserva', 0);
            $laDatos = [
                'Reserva' => $tnReserva,
                'Estado' => 2,
            ];
            if (isset($laDatosCore['ReservaExterna'])) {
                $laDatos['ReservaExterna'] = (string)$laDatosCore['ReservaExterna'];
            }
            return $laDatos;
        }

        if ($tcOperacion === 'RESERVA_CANCELAR') {
            $tnReserva = isset($laDatosCore['Reserva']) ? (int)$laDatosCore['Reserva'] : (int)$toRequest->input('Reserva', 0);
            $tnEstado = str_contains(mb_strtolower($tcMensaje), 'expirada') ? 3 : 4;
            $laDatos = [
                'Reserva' => $tnReserva,
                'Estado' => $tnEstado,
            ];
            if (isset($laDatosCore['ReservaExterna'])) {
                $laDatos['ReservaExterna'] = (string)$laDatosCore['ReservaExterna'];
            }
            return $laDatos;
        }

        return $laDatosCore;
    }

    /**
     * @return array<string,string>
     */
    private function extraerHeadersRepeticion(JsonResponse $toCore): array
    {
        $tcReplay = (string)$toCore->headers->get('X-Repeticion-Idempotencia', '');
        if (trim($tcReplay) === '') {
            return [];
        }

        return ['X-Repeticion-Idempotencia' => $tcReplay];
    }

    private function codigoErrorPorStatus(int $tnStatus): string
    {
        return match ($tnStatus) {
            400 => 'REQ_400',
            401 => 'AUTH_401',
            403 => 'AUTH_403',
            404 => 'NEG_404',
            409 => 'BUS_409',
            422 => 'VAL_422',
            default => 'API_' . $tnStatus,
        };
    }

    /**
     * @param array<string,mixed> $laDatos
     * @param array<int,array{Codigo:string,Campo:?string,Detalle:string}> $laErrores
     * @param array<string,mixed> $laMeta
     * @param array<string,string> $laHeaders
     */
    private function respuestaContrato(
        bool $lbOk,
        string $tcMensaje,
        array $laDatos = [],
        array $laErrores = [],
        array $laMeta = [],
        int $tnStatus = 200,
        array $laHeaders = []
    ): JsonResponse {
        return response()->json([
            'Ok' => $lbOk,
            'Mensaje' => $tcMensaje,
            'Datos' => $laDatos,
            'Errores' => $laErrores,
            'Meta' => $laMeta,
        ], $tnStatus, $laHeaders);
    }
}
