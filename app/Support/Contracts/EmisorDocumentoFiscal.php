<?php

namespace App\Support\Contracts;

/**
 * Contrato para emisores de documentos electrónicos fiscales (Colombia).
 * Implementaciones previstas: ARI (activa), SIIGO (alternativa), Facture (alternativa).
 * Todo el módulo de facturación depende de esta interface, NO de una implementación concreta.
 */
interface EmisorDocumentoFiscal
{
    /**
     * @param  array<int, array>  $lineas
     * @return array{cufe?:string, id_externo:string, estado_dian:string, xml_url?:string, pdf_url?:string}
     */
    public function emitirFactura(array $encabezado, array $lineas): array;

    /**
     * @param  array<int, array>  $lineas
     * @return array{id_externo:string, estado_dian:string}
     */
    public function emitirNotaCredito(string $facturaOrigenId, array $encabezado, array $lineas): array;

    /**
     * @param  array<int, array>  $facturas  Estructura ready-to-send.
     * @return array{lote_id:string, resultados: array<int, array>}
     */
    public function emitirLote(array $facturas): array;

    public function consultarEstado(string $idExterno): array;

    public function driver(): string;
}
