<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class JournalParametre extends Model
{
    protected $table = 'ci_journal_parametres';

    public $timestamps = false;

    protected $fillable = [
        'evenement',
        'entite',
        'entite_id',
        'compagnie_id',
        'compagnie_nom',
        'acteur_id',
        'acteur_nom',
        'cible_utilisateur_id',
        'cible_utilisateur_nom',
        'valeurs',
        'enregistre_le',
    ];

    protected function casts(): array
    {
        return [
            'valeurs' => 'array',
            'enregistre_le' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Le journal des paramètres est immuable.'));
        static::deleting(fn () => throw new LogicException('Le journal des paramètres est immuable.'));
    }

    public function libelleEvenement(): string
    {
        return match ($this->evenement) {
            'creation' => 'Création',
            'suppression' => 'Suppression',
            default => 'Modification',
        };
    }

    public function libelleEntite(): string
    {
        return match ($this->entite) {
            'cg_parametres' => 'Paramètres de gestion',
            'cg_charges_structure' => 'Charge de structure',
            'frais_regles' => 'Tarif de frais',
            'compagnies' => 'Paramètres de compagnie',
            'gares' => 'Paramètres de gare',
            'route_transports' => 'Tarif de trajet',
            'user_compagnie_roles' => 'Droits utilisateur',
            'modules_compagnies' => 'Module de compagnie',
            'modules_gares' => 'Module de gare',
            'users' => 'Compte utilisateur',
            default => Str::headline($this->entite),
        };
    }

    public function valeurLisible(mixed $valeur): string
    {
        if ($valeur === null || $valeur === '') {
            return '—';
        }

        if (is_bool($valeur)) {
            return $valeur ? 'Oui' : 'Non';
        }

        if (is_array($valeur)) {
            if (isset($valeur['nom'], $valeur['id'])) {
                return $valeur['nom'].' (#'.$valeur['id'].')';
            }

            return json_encode($valeur, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—';
        }

        return (string) $valeur;
    }
}
