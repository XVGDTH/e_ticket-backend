<?php

namespace App\Models;

use App\Services\Personnel\AccesPersonnel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodePaie extends Model
{
    protected $table = 'periodes_paie';

    protected $guarded = [];

    protected $casts = ['genere_le' => 'datetime', 'valide_le' => 'datetime', 'paye_le' => 'datetime'];

    public const STATUTS = ['brouillon' => 'Brouillon', 'validee' => 'Validée', 'payee' => 'Payée'];

    protected static function booted(): void
    {
        // La compagnie est imposée côté serveur sauf pour le super admin, qui la choisit dans le formulaire.
        static::creating(function (PeriodePaie $p) {
            $u = auth()->user();

            abort_unless(AccesPersonnel::paieGestion($u), 403);

            if (! $u->isSuperAdmin()) {
                $p->compagnie_id = $u->compagnieId();
            }
        });
    }

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class);
    }

    public function bulletins(): HasMany
    {
        return $this->hasMany(BulletinPaie::class);
    }

    public function debut(): Carbon
    {
        return Carbon::create($this->annee, $this->mois, 1)->startOfDay();
    }

    public function libelle(): string
    {
        return ucfirst($this->debut()->locale('fr')->translatedFormat('F Y'));
    }

    /** Le super admin voit tout ; l'admin de compagnie (avec ou sans ville) voit les périodes de sa compagnie. */
    public function scopeVisiblePour(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isAdminCompagnie()) {
            return $query->where('compagnie_id', $user->compagnieId() ?? 0);
        }

        return $query->whereRaw('1 = 0');
    }
}
