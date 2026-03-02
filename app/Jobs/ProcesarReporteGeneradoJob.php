<?php

namespace App\Jobs;

use App\Modulos\Analitica\Services\AnaliticaService;
use App\Modulos\Reporte\Services\ReporteService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProcesarReporteGeneradoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(private int $pnReporteGenerado)
    {
    }

    public function handle(ReporteService $toReporteService, AnaliticaService $toAnaliticaService): void
    {
        $loReporte = DB::connection('mysqlNegocio')
            ->table('REPORTEGENERADO')
            ->where('ReporteGenerado', $this->pnReporteGenerado)
            ->first();

        if (!$loReporte) {
            return;
        }

        $toReporteService->marcarProcesando($this->pnReporteGenerado);

        try {
            $tcTipo = strtoupper((string)$loReporte->TipoReporte);
            $tcFormato = strtoupper((string)$loReporte->Formato);
            $laFiltros = $this->decodeJson($loReporte->Filtros);
            $tnUsuario = (int)$loReporte->UsuarioSolicitante;

            $laDatos = $this->obtenerDatos($toAnaliticaService, $tcTipo, $laFiltros);

            $tcContenido = $this->generarContenido($laDatos, $tcFormato);
            $tcExtension = strtolower($tcFormato);
            $tcNombre = 'reporte_' . strtolower($tcTipo) . '_' . now()->format('YmdHis') . '_' . $this->pnReporteGenerado . '.' . $tcExtension;
            $tcRuta = 'reportes/' . $tcNombre;

            Storage::disk('public')->put($tcRuta, $tcContenido);

            $tnTamano = (int)Storage::disk('public')->size($tcRuta);
            $tcMime = $this->mimePorFormato($tcFormato);

            $toReporteService->marcarListo($this->pnReporteGenerado, $tcNombre, $tcRuta, $tcMime, $tnTamano, $tnUsuario);
        } catch (\Throwable $toEx) {
            $toReporteService->marcarError($this->pnReporteGenerado, $toEx->getMessage());
            throw $toEx;
        }
    }

    /** @param array<string,mixed> $laFiltros */
    private function obtenerDatos(AnaliticaService $toAnaliticaService, string $tcTipo, array $laFiltros): array
    {
        return match ($tcTipo) {
            'VENTAS' => $toAnaliticaService->ventas($laFiltros, 1, 10000)->items(),
            'ROTACION' => $toAnaliticaService->rotacion($laFiltros, 1, 10000)->items(),
            'STOCKOUT' => $toAnaliticaService->stockout($laFiltros, 1, 10000)->items(),
            'RENTABILIDAD' => $toAnaliticaService->rentabilidad($laFiltros, 1, 10000)->items(),
            'MERMAS' => $toAnaliticaService->mermas($laFiltros, 1, 10000)->items(),
            'RESUMEN' => [$toAnaliticaService->resumen($laFiltros)],
            default => throw new \RuntimeException('Tipo de reporte no soportado: ' . $tcTipo),
        };
    }

    /** @param array<int,mixed> $laDatos */
    private function generarContenido(array $laDatos, string $tcFormato): string
    {
        if ($tcFormato === 'CSV') {
            return $this->generarCsv($laDatos);
        }

        if ($tcFormato === 'XLSX') {
            // Contenido tabular compatible con apertura en Excel.
            return $this->generarTsv($laDatos);
        }

        if ($tcFormato === 'PDF') {
            return $this->generarPdfBasico($laDatos);
        }

        throw new \RuntimeException('Formato no soportado: ' . $tcFormato);
    }

    /** @param array<int,mixed> $laDatos */
    private function generarCsv(array $laDatos): string
    {
        if (count($laDatos) === 0) {
            return "Sin datos\n";
        }

        $laPrimero = (array)$laDatos[0];
        $laCabeceras = array_keys($laPrimero);

        $tf = fopen('php://temp', 'r+');
        if ($tf === false) {
            throw new \RuntimeException('No se pudo generar CSV');
        }

        fputcsv($tf, $laCabeceras);
        foreach ($laDatos as $tmFila) {
            $laFila = (array)$tmFila;
            $laValores = [];
            foreach ($laCabeceras as $tcCabecera) {
                $laValores[] = is_scalar($laFila[$tcCabecera] ?? null) || $laFila[$tcCabecera] === null
                    ? (string)($laFila[$tcCabecera] ?? '')
                    : json_encode($laFila[$tcCabecera], JSON_UNESCAPED_UNICODE);
            }
            fputcsv($tf, $laValores);
        }

        rewind($tf);
        $tc = stream_get_contents($tf);
        fclose($tf);

        return $tc !== false ? $tc : '';
    }

    /** @param array<int,mixed> $laDatos */
    private function generarTsv(array $laDatos): string
    {
        if (count($laDatos) === 0) {
            return "Sin datos\n";
        }

        $laPrimero = (array)$laDatos[0];
        $laCabeceras = array_keys($laPrimero);
        $laLineas = [implode("\t", $laCabeceras)];

        foreach ($laDatos as $tmFila) {
            $laFila = (array)$tmFila;
            $laValores = [];
            foreach ($laCabeceras as $tcCabecera) {
                $tm = $laFila[$tcCabecera] ?? '';
                if (!is_scalar($tm) && $tm !== null) {
                    $tm = json_encode($tm, JSON_UNESCAPED_UNICODE);
                }
                $laValores[] = str_replace(["\t", "\r", "\n"], ' ', (string)$tm);
            }
            $laLineas[] = implode("\t", $laValores);
        }

        return implode("\n", $laLineas) . "\n";
    }

    /** @param array<int,mixed> $laDatos */
    private function generarPdfBasico(array $laDatos): string
    {
        $laLineas = [];
        if (count($laDatos) === 0) {
            $laLineas[] = 'Sin datos';
        } else {
            $laPrimero = (array)$laDatos[0];
            $laCabeceras = array_keys($laPrimero);
            $laLineas[] = implode(' | ', $laCabeceras);
            $laLineas[] = str_repeat('-', 120);
            foreach ($laDatos as $tmFila) {
                $laFila = (array)$tmFila;
                $laValores = [];
                foreach ($laCabeceras as $tcCabecera) {
                    $tm = $laFila[$tcCabecera] ?? '';
                    if (!is_scalar($tm) && $tm !== null) {
                        $tm = json_encode($tm, JSON_UNESCAPED_UNICODE);
                    }
                    $laValores[] = str_replace(["\r", "\n"], ' ', (string)$tm);
                }
                $laLineas[] = implode(' | ', $laValores);
            }
        }

        $tcTexto = implode("\n", $laLineas);
        return $this->pdfDesdeTexto($tcTexto);
    }

    private function pdfDesdeTexto(string $tcTexto): string
    {
        $tcTexto = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $tcTexto);
        $laLineas = explode("\n", $tcTexto);

        $tcContenido = "BT\n/F1 10 Tf\n50 780 Td\n";
        foreach ($laLineas as $tnIdx => $tcLinea) {
            if ($tnIdx > 0) {
                $tcContenido .= "0 -12 Td\n";
            }
            $tcContenido .= '(' . $tcLinea . ") Tj\n";
        }
        $tcContenido .= "ET";

        $laObjetos = [];
        $laObjetos[] = "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj";
        $laObjetos[] = "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj";
        $laObjetos[] = "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj";
        $laObjetos[] = "4 0 obj << /Length " . strlen($tcContenido) . " >> stream\n" . $tcContenido . "\nendstream endobj";
        $laObjetos[] = "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj";

        $tcPdf = "%PDF-1.4\n";
        $laOffsets = [0];
        foreach ($laObjetos as $tcObj) {
            $laOffsets[] = strlen($tcPdf);
            $tcPdf .= $tcObj . "\n";
        }

        $tnXref = strlen($tcPdf);
        $tcPdf .= "xref\n0 " . (count($laObjetos) + 1) . "\n";
        $tcPdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($laObjetos); $i++) {
            $tcPdf .= str_pad((string)$laOffsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        $tcPdf .= "trailer << /Size " . (count($laObjetos) + 1) . " /Root 1 0 R >>\n";
        $tcPdf .= "startxref\n" . $tnXref . "\n%%EOF";

        return $tcPdf;
    }

    /** @return array<string,mixed> */
    private function decodeJson(mixed $tm): array
    {
        if (!is_string($tm) || trim($tm) === '') {
            return [];
        }
        $la = json_decode($tm, true);
        return is_array($la) ? $la : [];
    }

    private function mimePorFormato(string $tcFormato): string
    {
        return match ($tcFormato) {
            'CSV' => 'text/csv',
            'XLSX' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'PDF' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}
