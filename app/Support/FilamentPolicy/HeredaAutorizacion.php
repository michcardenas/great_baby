<?php

namespace App\Support\FilamentPolicy;

use Illuminate\Database\Eloquent\Model;

/**
 * Trait para Filament Resources: hereda la autorización de `canViewAny()` a
 * los verbos individuales (view/create/edit/delete/forceDelete/restore).
 *
 * Sin esto, Filament v3 default `canView/canEdit/canDelete = true`, y cualquier
 * autenticado puede acceder a URLs directas `/admin/{slug}/{id}/edit` bypasseando
 * el filtro de navegación.
 *
 * Aplicar en cada Resource: `use HeredaAutorizacion;`
 */
trait HeredaAutorizacion
{
    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    /**
     * Borrar NO se hereda de ver.
     *
     * Antes `canDelete()` devolvía `canViewAny()`, así que cualquiera con
     * permiso de lectura podía eliminar: un Vendedor —que tiene `ver.facturas`
     * para consultar las suyas— borraba facturas electrónicas ya reportadas a
     * la DIAN desde /admin, y lo mismo con la ficha de cualquier cliente.
     * Eliminar un documento fiscal es irreversible y descuadra la conciliación
     * con SIIGO, así que queda reservado a Aracely y Gerencia.
     */
    public static function canDelete(Model $record): bool
    {
        return \App\Auth\Permisos::esRoot(auth()->user());
    }

    public static function canDeleteAny(): bool
    {
        return \App\Auth\Permisos::esRoot(auth()->user());
    }

    public static function canForceDelete(Model $record): bool
    {
        return \App\Auth\Permisos::esRoot(auth()->user());
    }

    public static function canForceDeleteAny(): bool
    {
        return \App\Auth\Permisos::esRoot(auth()->user());
    }

    public static function canRestore(Model $record): bool
    {
        return \App\Auth\Permisos::esRoot(auth()->user());
    }

    public static function canRestoreAny(): bool
    {
        return \App\Auth\Permisos::esRoot(auth()->user());
    }

    public static function canReplicate(Model $record): bool
    {
        return static::canViewAny();
    }
}
