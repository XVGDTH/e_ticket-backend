<?php

namespace App\Filament\Resources\MarketingModeles\Pages;

use App\Filament\Resources\MarketingModeles\MarketingModeleResource;
use Filament\Resources\Pages\EditRecord;

class EditMarketingModele extends EditRecord
{
    protected static string $resource = MarketingModeleResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return MarketingModeleResource::securiser($data, $this->getRecord());
    }
}
