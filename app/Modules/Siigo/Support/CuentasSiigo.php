<?php

namespace App\Modules\Siigo\Support;

/**
 * Traduce un código del PUC del ERP al que realmente se manda a SIIGO.
 *
 * El plan de cuentas local y el de SIIGO son catálogos distintos: una cuenta
 * puede existir acá y no allá (`invalid_reference · The code doesn't exist`) o
 * existir con el mismo código pero ser una cuenta mayor (`account_not_allowed`).
 * SIIGO no publica su catálogo por API — /v1/accounts, /v1/chart-of-accounts y
 * /v1/puc responden 404 — así que el puente es el mapeo que la contadora llena
 * en Contabilidad → Plan de cuentas.
 *
 * Existía `ValidadorMapeoPucSiigo`, que resolvía bien y alimentaba la pantalla
 * de diagnóstico, pero los payloads mandaban el código crudo: la pantalla decía
 * "usá 22050501" y el asiento salía con `2205`. Este helper es el que usan los
 * payloads, y resuelve con el MISMO validador para que lo aprobado en pantalla
 * sea exactamente lo que viaja a SIIGO.
 */
class CuentasSiigo
{
    /** @var array<string,string> caché por request: código local → código SIIGO */
    private static array $resueltos = [];

    /**
     * Código para `account.code`. Si no se puede resolver se manda el original
     * y que SIIGO conteste: preferimos un rechazo explicable a adivinar una
     * cuenta contable, que sería peor que fallar.
     */
    public static function codigo(?string $codigoLocal): string
    {
        $codigo = trim((string) $codigoLocal);
        if ($codigo === '') {
            return '';
        }

        if (! array_key_exists($codigo, self::$resueltos)) {
            try {
                $r = app(ValidadorMapeoPucSiigo::class)->resolver($codigo);
                self::$resueltos[$codigo] = $r['ok'] ? (string) $r['puc_final'] : $codigo;
            } catch (\Throwable) {
                self::$resueltos[$codigo] = $codigo;
            }
        }

        return self::$resueltos[$codigo];
    }

    /**
     * Cuentas que SIIGO va a rechazar, con el motivo en palabras. Permite
     * avisar antes de mandar en vez de descubrirlo en un job fallido.
     *
     * @param  list<string>  $codigos
     * @return list<array{codigo:string, motivo:string}>
     */
    public static function problemas(array $codigos): array
    {
        $codigos = array_values(array_unique(array_filter(array_map('trim', $codigos))));
        if (! $codigos) {
            return [];
        }

        $validador = app(ValidadorMapeoPucSiigo::class);

        $out = [];
        foreach ($codigos as $c) {
            $r = $validador->resolver($c);
            if (! $r['ok']) {
                $out[] = ['codigo' => $c, 'motivo' => (string) $r['mensaje']];
            }
        }

        return $out;
    }

    /** Para tests y para cuando la contadora acaba de editar el mapeo. */
    public static function olvidarMapa(): void
    {
        self::$resueltos = [];
    }
}
