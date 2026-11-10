<?php

namespace App\Filament\Resources\FraisRegles\Pages;

use App\Filament\Resources\FraisRegles\FraisRegleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFraisRegles extends ListRecords
{
    protected static string $resource = FraisRegleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nouveau tarif'),
        ];
    }
}
