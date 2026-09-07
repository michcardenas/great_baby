<?php

namespace App\Modules\Cartera\Filament\Resources\ContactoResource\Pages;

use App\Modules\Cartera\Actions\CalcularAntiguedadCartera;
use App\Modules\Cartera\Actions\ConsultarCredito;
use App\Modules\Cartera\Filament\Resources\ContactoResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewContacto extends ViewRecord
{
    protected static string $resource = ContactoResource::class;

    protected string $view = 'cartera.pages.view-contacto';

    public function getCredito(): array
    {
        return ConsultarCredito::run($this->record->id);
    }

    public function getAntiguedad(): array
    {
        return CalcularAntiguedadCartera::run($this->record->id);
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
