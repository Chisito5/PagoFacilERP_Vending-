<?php

namespace App\Soporte;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ArchivoStorageService
{
    private string $pcDiscoNube = 's3';

    private string $pcDiscoLocal = 'public';

    private string $pcRaiz = 'PagoFacil_VendingMachine';

    /** @var array<string,string> */
    private array $paCarpetasDominio = [
        'qr' => 'VendingMachineQr',
        'producto' => 'VEndingMachineproductosIMg',
        'diseno' => 'VEndingMachineproductosIMg',
        'fondo' => 'VendingMachineFondos',
        'maquina' => 'VendingMachineMaquinasImg',
        'merma' => 'VendingMachineMermasEvidencia',
        'reporte' => 'VendingMachineReportes',
    ];

    /**
     * @return array{RutaObjeto:string,UrlPublica:string,Disco:string}
     */
    public function subirArchivo(UploadedFile $toArchivo, string $tcDominio, int $tnEmpresa, string $tcEntidad, int $tnEntidad): array
    {
        $tcExtension = strtolower((string)($toArchivo->getClientOriginalExtension() ?: $toArchivo->guessExtension() ?: 'bin'));
        $tcNombre = $this->generarNombreArchivo($tcDominio, $tcEntidad, $tnEntidad, $tcExtension);
        $tcPrefijo = $this->generarPrefijo($tcDominio, $tnEmpresa, $tcEntidad, $tnEntidad);

        Storage::disk($this->pcDiscoNube)->putFileAs($tcPrefijo, $toArchivo, $tcNombre, [
            'visibility' => 'public',
        ]);

        $tcRutaObjeto = $tcPrefijo . '/' . $tcNombre;

        return [
            'RutaObjeto' => $tcRutaObjeto,
            'UrlPublica' => $this->resolverUrl($tcRutaObjeto),
            'Disco' => $this->obtenerDiscoRuta($tcRutaObjeto),
        ];
    }

    /**
     * @return array{RutaObjeto:string,UrlPublica:string,Disco:string,TamanoBytes:int}
     */
    public function guardarContenido(string $tcContenido, string $tcDominio, int $tnEmpresa, string $tcEntidad, int $tnEntidad, string $tcExtension, string $tcMime): array
    {
        $tcNombre = $this->generarNombreArchivo($tcDominio, $tcEntidad, $tnEntidad, strtolower(trim($tcExtension)));
        $tcPrefijo = $this->generarPrefijo($tcDominio, $tnEmpresa, $tcEntidad, $tnEntidad);
        $tcRutaObjeto = $tcPrefijo . '/' . $tcNombre;

        Storage::disk($this->pcDiscoNube)->put($tcRutaObjeto, $tcContenido, [
            'visibility' => 'public',
            'ContentType' => $tcMime,
        ]);

        return [
            'RutaObjeto' => $tcRutaObjeto,
            'UrlPublica' => $this->resolverUrl($tcRutaObjeto),
            'Disco' => $this->obtenerDiscoRuta($tcRutaObjeto),
            'TamanoBytes' => max(0, strlen($tcContenido)),
        ];
    }

    public function resolverUrl(?string $tcRuta): string
    {
        $tcRuta = trim((string)$tcRuta);
        if ($tcRuta === '') {
            return '';
        }

        if (preg_match('/^https?:\\/\\//i', $tcRuta) === 1) {
            return $tcRuta;
        }

        $tcDisco = $this->obtenerDiscoRuta($tcRuta);
        return (string)Storage::disk($tcDisco)->url($tcRuta);
    }

    public function obtenerDiscoRuta(?string $tcRuta): string
    {
        $tcRuta = trim((string)$tcRuta);
        if ($tcRuta !== '' && str_starts_with($tcRuta, $this->pcRaiz . '/')) {
            return $this->pcDiscoNube;
        }

        return $this->pcDiscoLocal;
    }

    public function existe(?string $tcRuta): bool
    {
        $tcRuta = trim((string)$tcRuta);
        if ($tcRuta === '' || preg_match('/^https?:\\/\\//i', $tcRuta) === 1) {
            return false;
        }

        try {
            return Storage::disk($this->obtenerDiscoRuta($tcRuta))->exists($tcRuta);
        } catch (\Throwable) {
            return false;
        }
    }

    public function eliminar(?string $tcRuta): void
    {
        $tcRuta = trim((string)$tcRuta);
        if ($tcRuta === '' || preg_match('/^https?:\\/\\//i', $tcRuta) === 1) {
            return;
        }

        $tcDisco = $this->obtenerDiscoRuta($tcRuta);
        try {
            Storage::disk($tcDisco)->delete($tcRuta);
        } catch (\Throwable) {
            // Sin excepcion para permitir limpieza resiliente.
        }
    }

    public function tamano(?string $tcRuta): int
    {
        $tcRuta = trim((string)$tcRuta);
        if ($tcRuta === '' || preg_match('/^https?:\\/\\//i', $tcRuta) === 1) {
            return 0;
        }

        $tcDisco = $this->obtenerDiscoRuta($tcRuta);
        try {
            return (int)Storage::disk($tcDisco)->size($tcRuta);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function generarNombreArchivo(string $tcDominio, string $tcEntidad, int $tnEntidad, string $tcExtension): string
    {
        $tcModulo = $this->normalizarToken($tcDominio);
        $tcEntidadNombre = $this->normalizarToken($tcEntidad);
        $tcFecha = now()->format('YmdHis');
        $tcHash = bin2hex(random_bytes(4));
        $tcExtension = trim($tcExtension) !== '' ? $this->normalizarToken($tcExtension) : 'bin';

        return $tcModulo . '_' . $tcEntidadNombre . '_' . $tnEntidad . '_' . $tcFecha . '_' . $tcHash . '.' . $tcExtension;
    }

    private function generarPrefijo(string $tcDominio, int $tnEmpresa, string $tcEntidad, int $tnEntidad): string
    {
        $tcCarpetaDominio = $this->paCarpetasDominio[$tcDominio] ?? $this->paCarpetasDominio['producto'];
        $tnEmpresa = max(0, $tnEmpresa);
        $tcEntidad = $this->normalizarToken($tcEntidad);

        return $this->pcRaiz . '/' . $tcCarpetaDominio . '/empresa_' . $tnEmpresa . '/' . $tcEntidad . '_' . $tnEntidad;
    }

    private function normalizarToken(string $tcValor): string
    {
        $tcValor = strtolower(trim($tcValor));
        $tcValor = preg_replace('/[^a-z0-9]+/i', '_', $tcValor) ?? '';
        $tcValor = trim($tcValor, '_');
        return $tcValor !== '' ? $tcValor : 'archivo';
    }
}
