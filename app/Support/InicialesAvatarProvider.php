<?php

namespace App\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * Avatar de iniciales generado como data-URI SVG.
 * Reemplaza a UiAvatarsProvider (ui-avatars.com) para funcionar sin internet
 * y evitar 500/404 en la consola del navegador.
 */
class InicialesAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $name = trim((string) ($record->name ?? 'User'));
        $words = preg_split('/\s+/', $name) ?: [];
        $iniciales = strtoupper(mb_substr($words[0] ?? '?', 0, 1) . mb_substr($words[1] ?? '', 0, 1));

        // Paleta consistente por nombre
        $hash = crc32($name);
        $palette = ['#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899', '#ef4444', '#14b8a6'];
        $color = $palette[$hash % count($palette)];

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80">
  <rect width="80" height="80" rx="40" fill="{$color}"/>
  <text x="40" y="52" text-anchor="middle" font-family="system-ui,sans-serif" font-size="34" font-weight="600" fill="#fff">{$iniciales}</text>
</svg>
SVG;

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
