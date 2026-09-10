<?php

namespace App\Auth;

use App\Models\User;

/**
 * Matriz ÚNICA de autorización · GREAT BABY ERP.
 *
 *   Fix demo A2 · antes cada Filament Resource, cada canAccessPanel(), cada
 *   helper del User (esAracely, esContable, esAlistador, esSac) tenía su
 *   propia lista de roles hard-coded. Consecuencia visible en auditoría:
 *
 *     - Contador no podía entrar al panel (canAccessPanel omitía Contador
 *       aunque esContable() sí lo reconocía)
 *     - SAC veía solo 3 opciones cuando la lógica esperaba 6+
 *     - Vendedor sin acceso ni a comisiones ni a CRM aunque tiene data
 *     - Cambiar el scope de un rol requería tocar 30+ archivos
 *
 *   Ahora una sola matriz declarativa. Cada Resource/Page llama
 *   `Permisos::puede($u, 'facturas')`. Cambio de scope = editar esta clase.
 */
final class Permisos
{
    /**
     * Sección → roles que pueden verla en el sidebar.
     *
     * Aracely y Gerencia son ROOT — no se enumeran aquí, pasan siempre.
     * El resto se declara explícito.
     */
    public const MATRIZ = [
        // OPERACIÓN DIARIA
        'torre_control'    => ['Gerente', 'Alistador', 'Contador', 'ServicioCliente', 'Vendedor'],
        'estacion_empaque' => ['Alistador'],
        'vista_alistador'  => ['Alistador'],
        'escanner_camara'  => ['Alistador'],
        'inventario_vivo'  => ['Gerente', 'Alistador', 'Contador'],

        // DROPI
        'dropi_dashboard'  => ['Gerente'],
        'dropi_pedidos'    => ['Gerente', 'Alistador'],
        'dropi_cortes'     => ['Gerente', 'Contador'],
        'dropi_wallet'     => ['Gerente', 'Contador'],
        'dropi_devoluciones' => ['Gerente', 'Alistador', 'ServicioCliente'],
        'dropi_discrepancias' => ['Gerente', 'Contador'],
        'dropi_reportes'   => ['Gerente', 'Contador'],

        // CARTERA
        'facturas'         => ['Gerente', 'Contador', 'Vendedor'],
        'pagos'            => ['Gerente', 'Contador'],
        'contactos'        => ['Gerente', 'ServicioCliente', 'Vendedor'],
        'cartera_dashboard' => ['Gerente', 'Contador'],
        'condiciones_credito' => ['Gerente', 'Contador'],
        'cobranzas'        => ['Gerente', 'Contador'],
        'excepciones_credito' => ['Gerente', 'Contador'],
        'crm'              => ['Gerente', 'ServicioCliente', 'Vendedor'],
        'comisiones'       => ['Gerente', 'Contador', 'Vendedor'],

        // CONTABILIDAD
        'contabilidad'     => ['Gerente', 'Contador'],
        'libro_diario'     => ['Gerente', 'Contador'],
        'reportes_contables' => ['Gerente', 'Contador'],

        // COMPRAS
        'compras_oc'       => ['Gerente'],
        'compras_recepcion' => ['Gerente', 'Alistador'],
        'compras_importacion' => ['Gerente'],
        'compras_reportes' => ['Gerente', 'Contador'],

        // INVENTARIO
        'traslados'        => ['Gerente', 'Alistador'],
        'tomas_fisicas'    => ['Gerente', 'Contador', 'Alistador'],
        'alertas_stock'    => ['Gerente', 'Alistador'],
        'kardex'           => ['Gerente', 'Contador', 'Alistador'],
        'stock_bodegas'    => ['Gerente', 'Alistador', 'Contador'],

        // CATÁLOGO
        'productos'        => ['Gerente'],
        'ubicaciones'      => ['Gerente'],
        'importar_productos' => ['Gerente'],

        // BANDEJAS + IMPORTACIONES
        'bandeja_importaciones' => ['Gerente', 'Contador', 'Alistador', 'ServicioCliente'],

        // SERVICIO Y GARANTÍAS
        'garantias'        => ['Gerente', 'ServicioCliente'],
        'servicio_cliente' => ['Gerente', 'ServicioCliente'],

        // CONFIGURACIÓN — solo root
        'empresa'          => [],
        'siigo'            => [],
        'usuarios'         => [],
        'plantillas'       => [],
    ];

    /**
     * ¿El user tiene acceso a esta sección?
     * Aracely y Gerencia siempre true (root).
     */
    public static function puede(?User $u, string $seccion): bool
    {
        if (! $u) return false;
        if (self::esRoot($u)) return true;
        $rolesPermitidos = self::MATRIZ[$seccion] ?? [];
        return $u->hasAnyRole($rolesPermitidos);
    }

    /**
     * ¿Es root? (Aracely dueña o Gerencia general)
     */
    public static function esRoot(?User $u): bool
    {
        return $u && $u->hasAnyRole(['Aracely', 'Gerencia']);
    }

    /**
     * ¿Puede entrar al panel Filament?
     * Todos los roles reconocidos pueden entrar; la matriz filtra qué ven adentro.
     */
    public static function puedeAccederPanel(?User $u): bool
    {
        return $u && $u->hasAnyRole([
            'Aracely', 'Gerencia', 'Gerente', 'Contador',
            'Vendedor', 'Alistador', 'ServicioCliente',
        ]);
    }

    /**
     * Lista de secciones que este user puede ver. Útil para armar menús.
     */
    public static function seccionesDe(?User $u): array
    {
        if (! $u) return [];
        return array_keys(array_filter(
            self::MATRIZ,
            fn ($_, $seccion) => self::puede($u, $seccion),
            ARRAY_FILTER_USE_BOTH
        ));
    }
}
