<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpresaConfig extends Model
{
    protected $table = 'empresa_config';

    protected $guarded = ['id'];

    public static function current(): self
    {
        $c = static::query()->first();
        return $c ?? static::create([
            'razon_social' => 'GREAT BABY S.A.S.',
            'nit' => '901.738.354-7',
            'regimen' => 'Responsable de IVA',
        ]);
    }

    public function nitSinDv(): string
    {
        $nit = (string) $this->nit;
        $pos = strpos($nit, '-');
        $sin = $pos !== false ? substr($nit, 0, $pos) : $nit;
        return (string) preg_replace('/[^0-9]/', '', $sin);
    }
}
