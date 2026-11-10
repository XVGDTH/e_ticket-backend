<?php

namespace App\Filament\Resources\VoyagesCars\Pages;

use App\Filament\Resources\VoyagesCars\VoyageCarResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVoyagesCars extends ListRecords
{
    protected static string $resource = VoyageCarResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Programmer un voyage')];
    }
}
