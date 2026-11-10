<?php

// ListMarketingModeles.php

namespace App\Filament\Resources\MarketingModeles\Pages;

use App\Filament\Resources\MarketingModeles\MarketingModeleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMarketingModeles extends ListRecords
{
    protected static string $resource = MarketingModeleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nouveau modèle')];
    }
}
