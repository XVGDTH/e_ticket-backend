<?php

// ListMarketingCampagnes.php

namespace App\Filament\Resources\MarketingCampagnes\Pages;

use App\Filament\Resources\MarketingCampagnes\MarketingCampagneResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMarketingCampagnes extends ListRecords
{
    protected static string $resource = MarketingCampagneResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nouvelle campagne')];
    }
}
