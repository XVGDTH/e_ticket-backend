<?php

namespace App\Filament\Resources\Billets\Pages;

use App\Filament\Resources\Billets\BilletResource;
use Filament\Resources\Pages\ListRecords;

class ListBillets extends ListRecords
{
    protected static string $resource = BilletResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}