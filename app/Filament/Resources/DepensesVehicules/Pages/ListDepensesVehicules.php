<?php

namespace App\Filament\Resources\DepensesVehicules\Pages;

use App\Filament\Resources\DepensesVehicules\DepenseVehiculeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDepensesVehicules extends ListRecords
{
    protected static string $resource = DepenseVehiculeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nouvelle dépense')];
    }
}
