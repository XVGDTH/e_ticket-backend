<?php

namespace App\Models\Concerns;

use App\Models\Gare;
use App\Models\User;
use App\Models\Ville;
use App\Services\AuditInterne\EnregistreurParametres;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

trait JournaliseParametres
{
    public function save(array $options = []): bool
    {
        return DB::transaction(function () use ($options): bool {
            return parent::save($options);
        });
    }

    public function delete()
    {
        return DB::transaction(function () {
            return parent::delete();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function champsJournalises(): array
    {
        return [];
    }

    protected static function bootJournaliseParametres(): void
    {
        static::created(fn (Model $model) => $model->journaliserParametres('creation'));
        static::updated(fn (Model $model) => $model->journaliserParametres('modification'));
        static::deleting(fn (Model $model) => $model->journaliserParametres('suppression'));
    }

    private function journaliserParametres(string $evenement): void
    {
        $champs = $this->champsJournalises();
        $attributs = $this->getAttributes();

        if ($evenement === 'creation' && array_diff(array_keys($champs), array_keys($attributs)) !== []) {
            $attributs = $this->newQuery()->whereKey($this->getKey())->first()?->getAttributes() ?? $attributs;
        }

        $changements = $this->getChanges();
        $valeurs = [];

        foreach ($champs as $champ => $libelle) {
            if ($evenement === 'modification' && ! array_key_exists($champ, $changements)) {
                continue;
            }

            $ancienne = $evenement === 'creation' ? null : $this->getRawOriginal($champ);
            $nouvelle = $evenement === 'suppression' ? null : ($attributs[$champ] ?? null);

            if ($evenement === 'creation' && ! array_key_exists($champ, $attributs)) {
                continue;
            }

            $valeurs[$champ] = [
                'libelle' => $libelle,
                'ancienne' => $ancienne,
                'nouvelle' => $nouvelle,
            ];
        }

        if ($valeurs === []) {
            return;
        }

        $compagnieId = $this->getAttribute('compagnie_id');
        $compagnieNom = null;
        $cibleUtilisateurId = null;

        if ($this->getTable() === 'compagnies') {
            $compagnieId = $this->getKey();
            $compagnieNom = $this->getAttribute('nom');
        } elseif ($this instanceof User) {
            $compagnieId = $this->compagnieId();
            $cibleUtilisateurId = (int) $this->getKey();
        }

        foreach ($valeurs as $champ => &$changement) {
            $changement['ancienne'] = $this->valeurJournalisee($champ, $changement['ancienne']);
            $changement['nouvelle'] = $this->valeurJournalisee($champ, $changement['nouvelle']);
        }
        unset($changement);

        EnregistreurParametres::enregistrer(
            evenement: $evenement,
            entite: $this->getTable(),
            entiteId: $this->getKey() ? (int) $this->getKey() : null,
            compagnieId: $compagnieId !== null ? (int) $compagnieId : null,
            valeurs: $valeurs,
            cibleUtilisateurId: $cibleUtilisateurId ?? (
                $this->getTable() === 'user_compagnie_roles' && $this->getAttribute('user_id')
                    ? (int) $this->getAttribute('user_id')
                    : null
            ),
            compagnieNom: $compagnieNom,
        );
    }

    private function valeurJournalisee(string $champ, mixed $valeur): mixed
    {
        if ($valeur === null || $this->getTable() !== 'user_compagnie_roles') {
            return $valeur;
        }

        [$nom, $libelle] = match ($champ) {
            'role_id' => [Role::query()->whereKey($valeur)->value('name'), 'Rôle'],
            'gare_id' => [Gare::query()->whereKey($valeur)->value('quartier'), 'Gare'],
            'ville_id' => [Ville::query()->whereKey($valeur)->value('nom'), 'Ville'],
            default => [null, null],
        };

        return $libelle === null ? $valeur : [
            'id' => (int) $valeur,
            'nom' => $nom ?? $libelle.' supprimé',
        ];
    }
}
