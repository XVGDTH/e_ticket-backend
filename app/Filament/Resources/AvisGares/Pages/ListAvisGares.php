<?php

namespace App\Filament\Resources\AvisGares\Pages;

use App\Filament\Resources\AvisGares\AvisGareResource;
use Filament\Resources\Pages\ListRecords;

class ListAvisGares extends ListRecords
{
    protected static string $resource = AvisGareResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}