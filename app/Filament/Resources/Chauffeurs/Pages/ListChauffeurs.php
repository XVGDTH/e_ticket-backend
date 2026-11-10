<?php

namespace App\Filament\Resources\Chauffeurs\Pages;

use App\Filament\Resources\Chauffeurs\ChauffeurResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListChauffeurs extends ListRecords
{
    protected static string $resource = ChauffeurResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nouveau chauffeur')];
    }
}
