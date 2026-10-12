<?php

namespace App\Filament\Resources\Compagnies\Pages;

use App\Filament\Resources\Compagnies\CompagnieResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCompagnies extends ListRecords
{
    protected static string $resource = CompagnieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
