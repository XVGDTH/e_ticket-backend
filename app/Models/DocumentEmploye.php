<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentEmploye extends Model
{
    protected $table = 'documents_employes';

    protected $guarded = [];

    protected $casts = ['expire_le' => 'date'];

    public const TYPES = [
        'cnib' => 'CNIB / passeport',
        'contrat' => 'Contrat signé',
        'diplome' => 'Diplôme / attestation',
        'casier' => 'Casier judiciaire',
        'certificat_medical' => 'Certificat médical / visite d\'embauche',
        'certificat_residence' => 'Certificat de résidence',
        'cnss' => 'Attestation CNSS',
        'permis' => 'Permis de conduire',
        'autre' => 'Autre',
    ];

    protected static function booted(): void
    {
        static::creating(function (DocumentEmploye $d) {
            $d->cree_par ??= auth()->id();
            $d->compagnie_id = Employe::whereKey($d->employe_id)->value('compagnie_id');
        });
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }

    public function jours(): ?int
    {
        return $this->expire_le ? Vehicule::jours(today(), $this->expire_le) : null;
    }
}
