<?php

namespace App\Filament\Resources\CongesEmployes\Pages;

use App\Filament\Resources\CongesEmployes\CongeEmployeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCongesEmployes extends ManageRecords
{
    protected static string $resource = CongeEmployeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nouvelle demande')];
    }
}
