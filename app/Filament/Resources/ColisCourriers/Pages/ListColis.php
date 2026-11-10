<?php

namespace App\Filament\Resources\ColisCourriers\Pages;

use App\Filament\Resources\ColisCourriers\ColisResource;
use App\Filament\Widgets\ColisBandeau;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListColis extends ListRecords
{
    protected static string $resource = ColisResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nouvel envoi')];
    }

    protected function getHeaderWidgets(): array
    {
        return [ColisBandeau::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
