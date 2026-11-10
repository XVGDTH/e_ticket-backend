<?php

// ViewMarketingCampagne.php

namespace App\Filament\Resources\MarketingCampagnes\Pages;

use App\Filament\Resources\MarketingCampagnes\MarketingCampagneResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMarketingCampagne extends ViewRecord
{
    protected static string $resource = MarketingCampagneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MarketingCampagneResource::actionLancer(),
            MarketingCampagneResource::actionTester(),
            EditAction::make(),
            MarketingCampagneResource::actionAnnuler(),
        ];
    }
}
