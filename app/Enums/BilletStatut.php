<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BilletStatut: string implements HasLabel, HasColor
{
    case Valide = 'valide';
    case Utilise = 'utilise';
    case Annule = 'annule';
    case Rembourse = 'rembourse';

    public function getLabel(): string
    {
        return match ($this) {
            self::Valide => 'Valide',
            self::Utilise => 'Utilisé',
            self::Annule => 'Annulé',
            self::Rembourse => 'Remboursé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Valide => 'info',
            self::Utilise => 'success',
            self::Annule => 'gray',
            self::Rembourse => 'warning',
        };
    }

    /** Statuts qui comptent dans le chiffre d'affaires et le remplissage. */
    public static function comptabilises(): array
    {
        return [self::Valide->value, self::Utilise->value];
    }

    /** Transitions autorisées depuis ce statut. */
    public function transitions(): array
    {
        return match ($this) {
            self::Valide => [self::Utilise, self::Annule, self::Rembourse],
            default => [],
        };
    }

    public function peutDevenir(self $nouveau): bool
    {
        return in_array($nouveau, $this->transitions(), true);
    }
}