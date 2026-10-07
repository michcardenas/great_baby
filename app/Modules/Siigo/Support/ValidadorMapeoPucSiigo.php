<?php

namespace App\Modules\Siigo\Support;

use Illuminate\Support\Facades\DB;

/**
 * CONT-C5 · Valida que cada PUC usado por un documento tenga una cuenta
 * auxiliar transaccional "resolvible" para SIIGO antes de intentar el push.
 *
 * Sin esto, SIIGO responde `account_not_allowed` después de 3-5 round-trips
 * (auth + validaciones previas) y Aracely tiene que leer logs técnicos para
 * descubrir qué cuenta falta configurar. Con este validador bloqueamos el
 * push temprano y le decimos exactamente qué setting o cuenta corregir.
 *
 * Prioridad de resolución (igual que PushDevolucionProveedorASiigo):
 *   1. Override por settings de Reglas · siigo.cta_cxp_proveedor_siigo etc.
 *   2. La propia cuenta en `plan_cuentas` con `permite_movimiento=1`.
 *   3. Primer descendiente activo con `permite_movimiento=1`.
 */
class ValidadorMapeoPucSiigo
{
    /** @var array<string,string> prefijo PUC → clave setting override */
    public const OVERRIDES_PREFIJOS = [
        '2205' => 'siigo.cta_cxp_proveedor_siigo',
        '1435' => 'siigo.cta_inventario_siigo',
        '2408' => 'siigo.cta_iva_dev_compra_siigo',
    ];

    /**
     * Resuelve una cuenta PUC a su auxiliar transaccional SIIGO. Devuelve
     * ['ok'=>bool, 'puc_final'=>?string, 'via'=>'setting|propia|descendiente|none',
     *  'setting_usado'=>?string, 'mensaje'=>?string].
     */
    public function resolver(string $puc): array
    {
        if ($puc === '') {
            return $this->fail($puc, 'PUC vacío');
        }

        // 0. Mapeo explícito cuenta por cuenta (`plan_cuentas.siigo_cuenta_id`).
        //    Manda sobre todo lo demás: es la contadora diciendo "esta cuenta
        //    del ERP se llama así en SIIGO". Es el único camino cuando el código
        //    local no existe allá (SIIGO: `invalid_reference · code doesn't exist`),
        //    porque SIIGO no publica su plan de cuentas por API.
        if ($explicito = $this->mapeoExplicito($puc)) {
            return [
                'ok' => true, 'puc_final' => $explicito, 'via' => 'mapeo',
                'setting_usado' => null, 'mensaje' => null,
            ];
        }

        // 1. Override de settings (editable por la contadora).
        foreach (self::OVERRIDES_PREFIJOS as $prefijo => $setting) {
            if (str_starts_with($puc, $prefijo)) {
                $val = (string) setting($setting);
                if ($val !== '') {
                    // Estos settings guardan el código tal como lo espera SIIGO,
                    // que tiene su propio plan de cuentas: puede ser más largo que
                    // el del ERP (p. ej. 1435010101, de 10 dígitos) y no figurar en
                    // `plan_cuentas`. Antes eso se tomaba como error y bloqueaba el
                    // push de una cuenta que SIIGO sí acepta. Sólo lo rechazamos
                    // cuando la cuenta existe localmente y es una cuenta mayor,
                    // porque ahí sí sabemos que SIIGO la va a rechazar.
                    $aux = DB::table('plan_cuentas')->where('codigo', $val)->first();
                    if ($aux && (int) $aux->permite_movimiento !== 1) {
                        return $this->fail($puc, "Setting {$setting}={$val} apunta a una cuenta mayor; SIIGO sólo acepta auxiliares.", $setting);
                    }

                    return [
                        'ok' => true, 'puc_final' => $val, 'via' => 'setting',
                        'setting_usado' => $setting, 'mensaje' => null,
                    ];
                }
            }
        }

        // 2. La propia cuenta transaccional.
        $raiz = DB::table('plan_cuentas')->where('codigo', $puc)->first();
        if ($raiz && (int) $raiz->permite_movimiento === 1 && (int) $raiz->activa === 1) {
            return ['ok' => true, 'puc_final' => $puc, 'via' => 'propia', 'setting_usado' => null, 'mensaje' => null];
        }

        // 3. Descendiente activa transaccional.
        $aux = DB::table('plan_cuentas')
            ->where('codigo', 'like', $puc.'%')
            ->where('permite_movimiento', 1)
            ->where('activa', 1)
            ->orderBy('codigo')
            ->value('codigo');
        if ($aux) {
            // La auxiliar elegida puede tener su propio mapeo a SIIGO (pasa cuando
            // la cuenta existe en el PUC local pero SIIGO la rechaza). Hay que
            // aplicarlo, si no la traducción se queda a mitad de camino.
            return [
                'ok' => true, 'puc_final' => $this->mapeoExplicito($aux) ?? $aux,
                'via' => 'descendiente', 'setting_usado' => null, 'mensaje' => null,
            ];
        }

        $tienePadre = $raiz ? '' : ' (ni siquiera existe el PUC en plan_cuentas)';
        $sugerencia = $this->sugerirSetting($puc);
        return $this->fail(
            $puc,
            "No hay cuenta auxiliar transaccional bajo PUC {$puc}{$tienePadre}."
                .($sugerencia ? " Sugerencia: configurar {$sugerencia} en /app/empresa/reglas." : ''),
            $sugerencia,
        );
    }

    /**
     * Valida una lista de PUCs. Devuelve diagnóstico agregado útil para el UI
     * y para bloquear el push temprano desde el handle del Job.
     */
    public function validarCuentas(array $pucs): array
    {
        $resultados = [];
        $faltantes = [];
        foreach (array_unique(array_filter($pucs)) as $puc) {
            $r = $this->resolver((string) $puc);
            $resultados[(string) $puc] = $r;
            if (! $r['ok']) $faltantes[] = ['puc' => (string) $puc, 'mensaje' => $r['mensaje'], 'setting_sugerido' => $r['setting_usado']];
        }
        return [
            'ok' => count($faltantes) === 0,
            'resultados' => $resultados,
            'faltantes' => $faltantes,
        ];
    }

    /** El código con que la contadora mapeó esta cuenta a SIIGO, si lo hay. */
    private function mapeoExplicito(string $puc): ?string
    {
        $valor = DB::table('plan_cuentas')
            ->where('codigo', $puc)
            ->whereNotNull('siigo_cuenta_id')
            ->where('siigo_cuenta_id', '!=', '')
            ->value('siigo_cuenta_id');

        return $valor ? trim((string) $valor) : null;
    }

    private function fail(string $puc, string $msg, ?string $setting = null): array
    {
        return ['ok' => false, 'puc_final' => null, 'via' => 'none', 'setting_usado' => $setting, 'mensaje' => $msg];
    }

    private function sugerirSetting(string $puc): ?string
    {
        foreach (self::OVERRIDES_PREFIJOS as $prefijo => $setting) {
            if (str_starts_with($puc, $prefijo)) return $setting;
        }
        return null;
    }
}
