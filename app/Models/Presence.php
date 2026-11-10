<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presence extends Model
{
    protected $table = 'presences_employes';

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'minutes_retard' => 'integer',
        'heures_sup_minutes' => 'integer',
    ];

    public const STATUTS = [
        'present' => 'Présent',
        'retard' => 'En retard',
        'mission' => 'En mission',
        'repos' => 'Repos',
        'ferie' => 'Jour férié',
        'conge' => 'Congé payé',
        'permission' => 'Permission',
        'maladie' => 'Maladie',
        'absent_justifie' => 'Absent justifié',
        'absent_injustifie' => 'Absent injustifié',
        'mise_a_pied' => 'Mise à pied',
        'conge_sans_solde' => 'Congé sans solde',
    ];

    public const COULEURS = [
        'present' => 'success', 'retard' => 'warning', 'mission' => 'info', 'repos' => 'gray', 'ferie' => 'gray',
        'conge' => 'info', 'permission' => 'info', 'maladie' => 'warning', 'absent_justifie' => 'warning',
        'absent_injustifie' => 'danger', 'mise_a_pied' => 'danger', 'conge_sans_solde' => 'gray',
    ];

    protected static function booted(): void
    {
        static::creating(fn (Presence $p) => $p->cree_par ??= auth()->id());

        static::saving(function (Presence $p) {
            $e = Employe::find($p->employe_id);
            abort_unless($e, 422, 'Employé introuvable.');

            $p->compagnie_id = $e->compagnie_id;
            $p->gare_id = $e->gare_id;

            $u = auth()->user();

            if ($u && $u->isAgentGare()) {
                abort_unless((int) $e->gare_id === (int) $u->gareId(), 403);

                $d = Carbon::parse($p->date)->startOfDay();
                abort_if($d->lt(today()->subDays(7)) || $d->gt(today()), 403, 'Un agent ne peut pointer que les 7 derniers jours.');
            }

            if ($p->heure_arrivee && in_array($p->statut, ['present', 'retard'], true)) {
                $debut = substr((string) ($e->horaire_debut ?: config('personnel.horaire.debut')), 0, 5);
                $arrivee = substr((string) $p->heure_arrivee, 0, 5);
                $ecart = (int) ((strtotime("1970-01-01 $arrivee") - strtotime("1970-01-01 $debut")) / 60);

                if ($ecart > (int) config('personnel.horaire.tolerance_minutes', 10)) {
                    $p->minutes_retard = $ecart;
                    $p->statut = 'retard';
                } else {
                    $p->minutes_retard = 0;
                    $p->statut = 'present';
                }
            } elseif ($p->statut !== 'retard') {
                $p->minutes_retard = 0;
            }
        });
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }

    public function gare(): BelongsTo
    {
        return $this->belongsTo(Gare::class);
    }

    public function scopeVisiblePour(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isAdminCompagnie()) {
            $query->where('compagnie_id', $user->compagnieId() ?? 0);

            if ($user->villeId()) {
                $query->whereIn('gare_id', $user->garesGerables()->select('gares.id'));
            }

            return $query;
        }

        if ($user->isAgentGare()) {
            return $query->where('gare_id', $user->gareId() ?? 0);
        }

        return $query->whereRaw('1 = 0');
    }
}
