<?php

namespace App\Filament\Resources\ComptesComptables\Pages;

use App\Filament\Resources\ComptesComptables\CompteComptableResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListComptesComptables extends ListRecords
{
    protected static string $resource = CompteComptableResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nouveau compte')];
    }
}
