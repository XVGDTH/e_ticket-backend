<?php

namespace App\Filament\Resources\PeriodesPaie\Pages;

use App\Filament\Resources\PeriodesPaie\PeriodePaieResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePeriodesPaie extends ManageRecords
{
    protected static string $resource = PeriodePaieResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Ouvrir une période de paie')];
    }
}
