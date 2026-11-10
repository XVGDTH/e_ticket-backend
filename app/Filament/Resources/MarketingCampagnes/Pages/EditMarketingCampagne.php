<?php

namespace App\Filament\Resources\MarketingCampagnes\Pages;

use App\Filament\Resources\MarketingCampagnes\MarketingCampagneResource;
use Filament\Resources\Pages\EditRecord;

class EditMarketingCampagne extends EditRecord
{
    protected static string $resource = MarketingCampagneResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return MarketingCampagneResource::securiser($data);
    }
}
