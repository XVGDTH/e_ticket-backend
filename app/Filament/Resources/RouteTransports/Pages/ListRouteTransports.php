<?php

namespace App\Filament\Resources\RouteTransports\Pages;

use App\Filament\Resources\RouteTransports\RouteTransportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRouteTransports extends ListRecords
{
    protected static string $resource = RouteTransportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
