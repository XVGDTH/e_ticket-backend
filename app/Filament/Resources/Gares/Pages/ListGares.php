<?php

namespace App\Filament\Resources\Gares\Pages;

use App\Filament\Resources\Gares\GareResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGares extends ListRecords
{
    protected static string $resource = GareResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
