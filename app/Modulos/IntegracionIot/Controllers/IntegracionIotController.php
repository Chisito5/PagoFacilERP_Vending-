<?php

namespace App\Modulos\IntegracionIot\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\IntegracionIot\Services\IntegracionIotService;
use App\Soporte\RespuestaApi;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegracionIotController extends Controller
{
    public function __construct(private IntegracionIotService $toService)
    {
    }

    public function Webhook(Request $toRequest): JsonResponse
    {
        $tcEventId = trim((string)$toRequest->header('X-IoT-EventId', ''));
        $tcTimestamp = trim((string)$toRequest->header('X-IoT-Timestamp', ''));
        $tcFirma = trim((string)$toRequest->header('X-IoT-Firma', ''));
        $tcOrigen = trim((string)$toRequest->header('X-IoT-Origen', ''));

        $laErrores = [];
        if ($tcEventId === '') {
            $laErrores[] = ['Codigo' => 'IOT_EVENTO_FALTANTE', 'Campo' => 'X-IoT-EventId', 'Detalle' => 'Debe enviar el identificador del evento IoT'];
        }
        if ($tcTimestamp === '') {
            $laErrores[] = ['Codigo' => 'IOT_TIMESTAMP_FALTANTE', 'Campo' => 'X-IoT-Timestamp', 'Detalle' => 'Debe enviar la marca de tiempo del evento IoT'];
        }
        if ($tcFirma === '') {
            $laErrores[] = ['Codigo' => 'IOT_FIRMA_FALTANTE', 'Campo' => 'X-IoT-Firma', 'Detalle' => 'Debe enviar la firma HMAC del evento IoT'];
        }
        if ($tcOrigen === '') {
            $laErrores[] = ['Codigo' => 'IOT_ORIGEN_FALTANTE', 'Campo' => 'X-IoT-Origen', 'Detalle' => 'Debe enviar el origen de la integracion IoT'];
        }
        if (count($laErrores) > 0) {
            return RespuestaApi::error('Encabezados IoT incompletos', 400, $laErrores);
        }

        $toFechaEvento = $this->resolverFechaEvento($tcTimestamp);
        if (!$toFechaEvento) {
            return RespuestaApi::error('Marca de tiempo IoT invalida', 400, [
                ['Codigo' => 'IOT_TIMESTAMP_INVALIDO', 'Campo' => 'X-IoT-Timestamp', 'Detalle' => 'No se pudo interpretar el timestamp IoT'],
            ]);
        }

        $tnVentana = max(1, (int)config('services.iot.ventana_segundos', 300));
        if (abs(now()->getTimestamp() - $toFechaEvento->getTimestamp()) > $tnVentana) {
            return RespuestaApi::error('Timestamp IoT fuera de ventana permitida', 401, [
                ['Codigo' => 'IOT_TIMESTAMP_FUERA_VENTANA', 'Campo' => 'X-IoT-Timestamp', 'Detalle' => 'La marca de tiempo no esta dentro de la ventana anti-replay'],
            ]);
        }

        $tcSecreto = (string)config('services.iot.clave_secreta', '');
        if (trim($tcSecreto) === '') {
            return RespuestaApi::error('Integracion IoT no configurada', 503, [
                ['Codigo' => 'IOT_SECRETO_FALTANTE', 'Campo' => null, 'Detalle' => 'Configure services.iot.clave_secreta en el entorno'],
            ]);
        }

        $tcRawBody = (string)$toRequest->getContent();
        if (!$this->validarFirma($tcSecreto, $tcEventId, $tcTimestamp, $tcRawBody, $tcFirma)) {
            return RespuestaApi::error('Firma IoT invalida', 401, [
                ['Codigo' => 'IOT_FIRMA_INVALIDA', 'Campo' => 'X-IoT-Firma', 'Detalle' => 'La firma no coincide con el payload recibido'],
            ]);
        }

        $laPayload = $toRequest->json()->all();
        if (!is_array($laPayload)) {
            $laPayload = [];
        }

        $la = $this->toService->registrarWebhook($tcOrigen, $tcEventId, $tcTimestamp, $tcFirma, $laPayload);
        if (($la['Estado'] ?? '') === 'IDEMPOTENTE') {
            return RespuestaApi::exito('Evento IoT ya recibido previamente', $la['Datos'] ?? []);
        }

        return RespuestaApi::exito('Evento IoT recibido y encolado', $la['Datos'] ?? [], 202);
    }

    public function ListarEventos(Request $toRequest): JsonResponse
    {
        $to = $this->toService->listar(
            $toRequest->filled('Origen') ? (string)$toRequest->query('Origen') : null,
            $toRequest->filled('Estado') ? (int)$toRequest->query('Estado') : null,
            (int)$toRequest->query('Pagina', 1),
            (int)$toRequest->query('TamanoPagina', 20)
        );

        return RespuestaApi::paginado('Listado de eventos IoT', $to);
    }

    public function ObtenerEvento(int $tnEvento): JsonResponse
    {
        $la = $this->toService->obtener($tnEvento);
        if (!$la) {
            return RespuestaApi::error('Evento IoT no encontrado', 404);
        }
        return RespuestaApi::exito('Detalle del evento IoT', $la);
    }

    private function resolverFechaEvento(string $tcTimestamp): ?Carbon
    {
        $tcTimestamp = trim($tcTimestamp);
        if ($tcTimestamp === '') {
            return null;
        }

        try {
            if (ctype_digit($tcTimestamp)) {
                return Carbon::createFromTimestampUTC((int)$tcTimestamp);
            }

            return Carbon::parse($tcTimestamp);
        } catch (\Throwable) {
            return null;
        }
    }

    private function validarFirma(string $tcSecreto, string $tcEventId, string $tcTimestamp, string $tcRawBody, string $tcFirmaRecibida): bool
    {
        $tcBase = $tcTimestamp . "\n" . $tcEventId . "\n" . $tcRawBody;
        $tcFirmaEsperada = hash_hmac('sha256', $tcBase, $tcSecreto);

        $tcFirmaRecibida = trim($tcFirmaRecibida);
        if (str_starts_with(strtolower($tcFirmaRecibida), 'sha256=')) {
            $tcFirmaRecibida = substr($tcFirmaRecibida, 7);
        }

        return hash_equals(strtolower($tcFirmaEsperada), strtolower($tcFirmaRecibida));
    }
}
