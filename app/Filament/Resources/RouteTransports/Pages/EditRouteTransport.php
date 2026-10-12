<?php

namespace App\Filament\Resources\RouteTransports\Pages;

use App\Filament\Resources\RouteTransports\RouteTransportResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRouteTransport extends EditRecord
{
    protected static string $resource = RouteTransportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
