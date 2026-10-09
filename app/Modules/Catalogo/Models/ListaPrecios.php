<?php

namespace App\Modules\Catalogo\Models;

use Illuminate\Database\Eloquent\Model;

class ListaPrecios extends Model
{
    protected $table = 'listas_precios';
    protected $fillable = ['codigo', 'nombre', 'canal', 'activa', 'predeterminada'];
    protected $casts = ['activa' => 'boolean', 'predeterminada' => 'boolean'];

    /**
     * Precios por variante de esta lista.
     *
     * Sirve para saber cuántos productos puede comprar de verdad un cliente
     * con esta lista asignada: varias listas existen con nombre y sin un solo
     * precio cargado, y eso sólo se descubría cuando el vendedor le buscaba
     * mercancía al cliente y no le aparecía nada.
     */
    public function precios(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PrecioVariante::class, 'lista_id');
    }
}
