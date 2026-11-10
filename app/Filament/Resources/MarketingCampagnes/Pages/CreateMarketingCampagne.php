<?php

// CreateMarketingCampagne.php

namespace App\Filament\Resources\MarketingCampagnes\Pages;

use App\Filament\Resources\MarketingCampagnes\MarketingCampagneResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMarketingCampagne extends CreateRecord
{
    protected static string $resource = MarketingCampagneResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return MarketingCampagneResource::securiser($data);
    }

    protected function getRedirectUrl(): string
    {
        return MarketingCampagneResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
