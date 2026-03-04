<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('reservas:expirar')->everyMinute();
        $schedule->command('alertas:generar')->everyMinute();
        $schedule->command('iot:evento:reencolar --max-intentos=5 --limite=200')->everyFiveMinutes();
        $schedule->command('idempotencia:limpiar --dias=7')->daily();
        $schedule->command('reportes:limpiar --dias=7')->daily();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('api', [
            \App\Http\Middleware\NormalizarContratoApi::class,
        ]);

        $middleware->alias([
            'idempotencia.requerida' => \App\Http\Middleware\RequiereClaveIdempotencia::class,
            'erp.v1.habilitado' => \App\Http\Middleware\BloquearErpV1Deshabilitado::class,
            'api.correlacion' => \App\Http\Middleware\CorrelacionApiMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $toException, $toRequest) {
            if (!$toRequest->is('api/*')) {
                return null;
            }

            $laErrores = [];
            foreach ($toException->errors() as $tcCampo => $laDetalle) {
                foreach ($laDetalle as $tcDetalle) {
                    $laErrores[] = [
                        'Codigo' => 'VALIDACION',
                        'Campo' => (string)$tcCampo,
                        'Detalle' => (string)$tcDetalle,
                    ];
                }
            }

            return response()->json([
                'Ok' => false,
                'Mensaje' => 'Errores de validacion',
                'Datos' => [],
                'Errores' => $laErrores,
                'Meta' => [],
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $toException, $toRequest) {
            if (!$toRequest->is('api/*')) {
                return null;
            }

            return response()->json([
                'Ok' => false,
                'Mensaje' => 'No autenticado',
                'Datos' => [],
                'Errores' => [
                    [
                        'Codigo' => 'AUTH_401',
                        'Campo' => null,
                        'Detalle' => 'Debe iniciar sesion para acceder a este recurso',
                    ]
                ],
                'Meta' => [],
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $toException, $toRequest) {
            if (!$toRequest->is('api/*')) {
                return null;
            }

            return response()->json([
                'Ok' => false,
                'Mensaje' => 'No autorizado',
                'Datos' => [],
                'Errores' => [
                    [
                        'Codigo' => 'AUTH_403',
                        'Campo' => null,
                        'Detalle' => 'No tiene permisos para ejecutar esta operacion',
                    ]
                ],
                'Meta' => [],
            ], 403);
        });

        $exceptions->render(function (\Throwable $toException, $toRequest) {
            if (!$toRequest->is('api/*')) {
                return null;
            }

            $tnCodigo = 500;
            $tcMensaje = 'Error interno del servidor';

            if ($toException instanceof HttpExceptionInterface) {
                $tnCodigo = $toException->getStatusCode();
                if ($tnCodigo < 500) {
                    $tcMensaje = $toException->getMessage() !== ''
                        ? $toException->getMessage()
                        : 'Solicitud invalida';
                }
            }

            return response()->json([
                'Ok' => false,
                'Mensaje' => $tcMensaje,
                'Datos' => [],
                'Errores' => [
                    [
                        'Codigo' => 'API_' . $tnCodigo,
                        'Campo' => null,
                        'Detalle' => $tnCodigo >= 500 ? 'Ocurrio un error no controlado' : $tcMensaje,
                    ]
                ],
                'Meta' => [],
            ], $tnCodigo);
        });
    })->create();
