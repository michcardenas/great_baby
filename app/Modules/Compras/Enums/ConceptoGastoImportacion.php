<?php

namespace App\Modules\Compras\Enums;

enum ConceptoGastoImportacion: string
{
    case Flete = 'flete';
    case Seguro = 'seguro';
    case Arancel = 'arancel';
    case IvaImportacion = 'iva_importacion';
    case AgenteAduana = 'agente_aduana';
    case Almacenaje = 'almacenaje';
    case TransporteInterno = 'transporte_interno';
    case Otros = 'otros';

    public function label(): string
    {
        return match ($this) {
            self::Flete => 'Flete internacional',
            self::Seguro => 'Seguro',
            self::Arancel => 'Arancel',
            self::IvaImportacion => 'IVA de importación',
            self::AgenteAduana => 'Agente aduanal',
            self::Almacenaje => 'Almacenaje',
            self::TransporteInterno => 'Transporte interno',
            self::Otros => 'Otros gastos',
        };
    }

    public function capitalizablePorDefecto(): bool
    {
        return match ($this) {
            self::IvaImportacion => false,
            default => true,
        };
    }
}
