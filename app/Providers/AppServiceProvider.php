<?php

namespace App\Providers;

use App\Models\Usuario;
use App\Support\EstadoCatalogo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        require_once base_path('routes/channels.php');

        Auth::viaRequest('api_negocio', function ($toRequest): ?Usuario {
            $tcToken = trim((string)$toRequest->bearerToken());
            if ($tcToken === '') {
                return null;
            }

            $tnEstadoActivo = 1;
            try {
                /** @var EstadoCatalogo $toEstadoCatalogo */
                $toEstadoCatalogo = app(EstadoCatalogo::class);
                $tnEstadoActivo = $toEstadoCatalogo->obtenerId('GENERAL', 1);
            } catch (RuntimeException) {
                $tnEstadoActivo = 1;
            }

            $tcHashAcceso = hash('sha256', $tcToken);
            $tdAhora = now();

            if (!Schema::connection('mysqlNegocio')->hasTable('SESIONAPI')) {
                return null;
            }

            $loSesion = DB::connection('mysqlNegocio')
                ->table('SESIONAPI')
                ->where('HashTokenAcceso', $tcHashAcceso)
                ->where('Estado', $tnEstadoActivo)
                ->whereNull('FechaHoraRevocacion')
                ->where('ExpiraAccesoEn', '>', $tdAhora)
                ->first();

            if (!$loSesion) {
                return null;
            }

            /** @var Usuario|null $toUsuario */
            $toUsuario = Usuario::on('mysqlNegocio')
                ->where('Usuario', (int)$loSesion->Usuario)
                ->where('Estado', $tnEstadoActivo)
                ->first();

            if (!$toUsuario) {
                return null;
            }

            DB::connection('mysqlNegocio')
                ->table('SESIONAPI')
                ->where('SesionApi', (int)$loSesion->SesionApi)
                ->update([
                    'UltimoUsoEn' => $tdAhora,
                ]);

            $toUsuario->setAttribute('SesionApi', (int)$loSesion->SesionApi);

            return $toUsuario;
        });
    }
}
