<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BilletStatut: string implements HasColor, HasLabel
{
    case Valide = 'valide';
    case Utilise = 'utilise';
    case Annule = 'annule';
    case Rembourse = 'rembourse';
    case EnAttentePaiement = 'en_attente_paiement';

    public function getLabel(): string
    {
        return match ($this) {
            self::Valide => 'Valide',
            self::Utilise => 'Utilisé',
            self::Annule => 'Annulé',
            self::Rembourse => 'Remboursé',
            self::EnAttentePaiement => 'En attente de paiement'
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Valide => 'info',
            self::Utilise => 'success',
            self::Annule => 'gray',
            self::Rembourse => 'warning',
            self::EnAttentePaiement => 'warning'

        };
    }

    /** Statuts qui comptent dans le chiffre d'affaires et le remplissage. */
    public static function comptabilises(): array
    {
        return [self::Valide->value, self::Utilise->value];

    }

    /** Transitions autorisées depuis ce statut. */
    /** Transitions autorisées depuis ce statut. */
    public function transitions(): array
    {
        // return match ($this) {
        //     self::Valide => [self::Utilise, self::Annule, self::Rembourse],
        //     self::EnAttentePaiement => [self::Valide, self::Annule],
        //     self::Utilise
        //     default => [],
        // };
        return match ($this) {
            self::Valide => [self::Utilise, self::Annule, self::Rembourse],
            self::Utilise => [self::Valide], // correction d'une erreur d'embarquement
            self::EnAttentePaiement => [self::Valide, self::Annule],
            default => [],
        };
    }

    public function peutDevenir(self $nouveau): bool
    {
        return in_array($nouveau, $this->transitions(), true);
    }
}
