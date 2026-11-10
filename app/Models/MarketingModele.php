<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingModele extends Model
{
    protected $table = 'marketing_modeles';

    protected $guarded = [];

    protected $casts = ['exemples' => 'array', 'bouton_stop' => 'boolean', 'soumis_le' => 'datetime'];

    /** Variables remplies automatiquement pour chaque client, avec leur exemple pour Meta et l'aperçu. */
    public const AUTO = [
        'prenom' => 'Fatou',
        'nom' => 'Zougrana',
        'compagnie' => 'STAF',
        'gare' => 'Gounghin',
        'billets' => '4',
    ];

    public const STATUTS = [
        'brouillon' => 'Brouillon',
        'en_attente' => 'En examen chez Meta',
        'approuve' => 'Approuvé',
        'refuse' => 'Refusé',
        'pause' => 'En pause',
    ];

    /** Variables dans l'ordre d'apparition, une entrée par occurrence. */
    public static function occurrences(string $corps): array
    {
        preg_match_all('/\{([a-z][a-z0-9_]*)\}/', $corps, $m);

        return $m[1];
    }

    /** Variables à remplir à la main dans chaque campagne. */
    public static function personnalisees(string $corps): array
    {
        return array_values(array_unique(array_diff(self::occurrences($corps), array_keys(self::AUTO))));
    }

    /** Le texte tel que Meta le veut : {prenom} devient {{1}}, {offre} devient {{2}}… */
    public static function versMeta(string $corps): string
    {
        $i = 0;

        return preg_replace_callback('/\{[a-z][a-z0-9_]*\}/', function () use (&$i) {
            return '{{'.++$i.'}}';
        }, $corps);
    }

    public static function rendre(string $corps, array $valeurs): string
    {
        return preg_replace_callback('/\{([a-z][a-z0-9_]*)\}/', fn ($m) => (string) ($valeurs[$m[1]] ?? $m[0]), $corps);
    }

    /** Un exemple par occurrence, exigé par Meta à la soumission. */
    public function exemplesMeta(): array
    {
        return array_map(
            fn ($nom) => (string) (($this->exemples[$nom] ?? null) ?: (self::AUTO[$nom] ?? 'exemple')),
            self::occurrences($this->corps)
        );
    }

    /** Ce que le formulaire et la campagne montrent dans le faux téléphone. */
    public static function apercu(int $modeleId, array $variables = []): array
    {
        $m = $modeleId ? self::find($modeleId) : null;

        if (! $m) {
            return ['corps' => '', 'pied' => null, 'stop' => false];
        }

        $valeurs = self::AUTO;

        foreach ($variables as $nom => $valeur) {
            if (filled($valeur)) {
                $valeurs[$nom] = $valeur;
            }
        }

        return ['corps' => self::rendre($m->corps, $valeurs), 'pied' => $m->pied, 'stop' => (bool) $m->bouton_stop];
    }

    /** Ce qui ferait refuser le modèle par Meta : renvoie le message d'erreur, ou null si tout va bien. */
    public static function verifier(string $corps, ?string $pied, array $exemples): ?string
    {
        $corps = trim($corps);

        if (mb_strlen($corps) < 20) {
            return 'Le message est trop court.';
        }

        if (mb_strlen(self::versMeta($corps)) > 1024) {
            return 'Le message dépasse 1 024 caractères.';
        }

        if (preg_match('/^\s*\{[a-z]/', $corps) || preg_match('/\}\s*$/', $corps)) {
            return 'Le message ne peut pas commencer ni se terminer par une variable : ajoutez du texte avant et après.';
        }

        if (preg_match('/\}\s*\{/', $corps)) {
            return 'Deux variables ne peuvent pas se suivre : séparez-les par du texte.';
        }

        if (count(self::occurrences($corps)) > 6) {
            return 'Six variables au maximum.';
        }

        if ($pied !== null && mb_strlen($pied) > 60) {
            return 'Le pied de message est limité à 60 caractères.';
        }

        foreach (self::personnalisees($corps) as $nom) {
            if (blank($exemples[$nom] ?? null)) {
                return 'Donnez un exemple pour la variable {'.$nom.'}.';
            }
        }

        return null;
    }
}
