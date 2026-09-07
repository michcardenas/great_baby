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

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDeleteAny(): bool
    {
        return static::canViewAny();
    }

    public static function canForceDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canForceDeleteAny(): bool
    {
        return static::canViewAny();
    }

    public static function canRestore(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canRestoreAny(): bool
    {
        return static::canViewAny();
    }

    public static function canReplicate(Model $record): bool
    {
        return static::canViewAny();
    }
}
