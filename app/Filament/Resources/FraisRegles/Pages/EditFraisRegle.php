<?php

namespace App\Filament\Resources\FraisRegles\Pages;

use App\Filament\Resources\FraisRegles\FraisRegleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFraisRegle extends EditRecord
{
    protected static string $resource = FraisRegleResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return FraisRegleResource::securiser($data);
    }
}
