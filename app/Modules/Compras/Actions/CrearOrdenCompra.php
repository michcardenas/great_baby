<?php

namespace App\Modules\Compras\Actions;

use App\Modules\Compras\Enums\EstadoOrdenCompra;
use App\Modules\Compras\Models\OrdenCompra;
use App\Modules\Compras\Models\OrdenCompraItem;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CrearOrdenCompra
{
    use AsAction;

    /**
     * @param array{proveedor_id:int, bodega_id?:int|null, tipo?:string, moneda?:string,
     *              tasa_cambio?:float, fecha_esperada?:string|null, observaciones?:string|null,
     *              items:array<int,array{producto_id?:int|null,variante_id?:int|null,
     *                    descripcion:string,cantidad:float,precio_unit:float,
     *                    descuento_pct?:float,iva_pct?:float}>} $data
     */
    public function handle(array $data): OrdenCompra
    {
        return DB::transaction(function () use ($data) {
            $orden = OrdenCompra::create([
                'numero' => OrdenCompra::siguienteNumero(),
                'proveedor_id' => $data['proveedor_id'],
                'bodega_id' => $data['bodega_id'] ?? null,
                'condicion_credito_id' => $data['condicion_credito_id'] ?? null,
                'tipo' => $data['tipo'] ?? 'nacional',
                'moneda' => $data['moneda'] ?? 'COP',
                'tasa_cambio' => $data['tasa_cambio'] ?? 1,
                'fecha_emision' => now(),
                'fecha_esperada' => $data['fecha_esperada'] ?? null,
                'estado' => EstadoOrdenCompra::Borrador,
                'creado_por' => auth()->id(),
                'observaciones' => $data['observaciones'] ?? null,
            ]);

            foreach ($data['items'] as $it) {
                OrdenCompraItem::create([
                    'orden_id' => $orden->id,
                    'producto_id' => $it['producto_id'] ?? null,
                    'variante_id' => $it['variante_id'] ?? null,
                    'descripcion' => $it['descripcion'],
                    'cantidad' => $it['cantidad'],
                    'precio_unit' => $it['precio_unit'],
                    'descuento_pct' => $it['descuento_pct'] ?? 0,
                    'iva_pct' => $it['iva_pct'] ?? 19,
                ]);
            }

            RecalcularTotalesOC::make()->handle($orden);

            return $orden->fresh(['items', 'proveedor']);
        });
    }
}
