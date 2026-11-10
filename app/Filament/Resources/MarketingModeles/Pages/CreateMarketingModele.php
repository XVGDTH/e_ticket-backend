<?php

namespace App\Filament\Resources\MarketingModeles\Pages;

use App\Filament\Resources\MarketingModeles\MarketingModeleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMarketingModele extends CreateRecord
{
    protected static string $resource = MarketingModeleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return MarketingModeleResource::securiser($data);
    }
}
