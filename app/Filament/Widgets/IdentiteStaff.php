<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class IdentiteStaff extends Widget
{
    protected string $view = 'filament.widgets.identite-staff';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;

    protected function getViewData(): array
    {
        $utilisateur = auth()->user();
        $profil = $utilisateur->profilStaff();
        $compagnie = $utilisateur->compagnieActuelle();

        return [
            'nom' => $utilisateur->getFilamentName(),
            'role' => $profil['role'],
            'compagnie' => $compagnie?->nom,
            'logo' => $compagnie?->logo_affichable,
            'gare' => $profil['gare'],
            'ville' => $profil['ville'],
        ];
    }
}
