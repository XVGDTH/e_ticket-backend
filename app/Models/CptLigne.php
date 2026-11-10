<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CptLigne extends Model
{
    public $timestamps = false;

    protected $table = 'cpt_lignes';

    protected $guarded = [];

    protected $casts = ['valide_le' => 'datetime', 'debit' => 'integer', 'credit' => 'integer'];

    protected static function booted(): void
    {
        // Les lignes d'une écriture comptabilisée ne bougent plus.
        $garde = function (CptLigne $ligne) {
            if (CptEcriture::whereKey($ligne->ecriture_id)->where('statut', 'comptabilise')->exists()) {
                throw new \DomainException('Cette écriture est comptabilisée : ses lignes ne peuvent plus changer.');
            }
        };

        static::updating($garde);
        static::deleting($garde);

        // Une ligne validée est figée : seule la validation elle-même (valide_le / valide_par) peut l'écrire.
        static::updating(function (CptLigne $l) {
            if ($l->getOriginal('valide_le') && $l->isDirty(['compte_id', 'debit', 'credit', 'section_id', 'libelle'])) {
                throw new \DomainException('Cette ligne est déjà validée : elle ne peut plus être modifiée.');
            }
        });

        static::deleting(function (CptLigne $l) {
            if ($l->valide_le) {
                throw new \DomainException('Une ligne validée ne peut pas être supprimée.');
            }
        });
    }

    public function ecriture(): BelongsTo
    {
        return $this->belongsTo(CptEcriture::class, 'ecriture_id');
    }

    public function compte(): BelongsTo
    {
        return $this->belongsTo(CptCompte::class, 'compte_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(CptSection::class, 'section_id');
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function imputations(): HasMany
    {
        return $this->hasMany(CptImputation::class, 'ligne_id');
    }

    public function montant(): int
    {
        return max((int) $this->debit, (int) $this->credit);
    }
}
