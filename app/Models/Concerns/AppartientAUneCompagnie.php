<?php

namespace App\Models\Concerns;

trait AppartientAUneCompagnie
{
    protected static function bootAppartientAUneCompagnie(): void
    {
        // À la création : un non super admin ne crée que pour sa compagnie (et sa ville s'il en a une).
        static::creating(function ($model) {
            $user = auth()->user();

            if ($user && ! $user->isSuperAdmin() && $user->compagnieId()) {
                $model->compagnie_id = $user->compagnieId();

                if ($user->villeId() && in_array('ville_id', $model->getFillable(), true)) {
                    $model->ville_id = $user->villeId();
                }
            }
        });

        // À la modification : impossible de déplacer un enregistrement vers une autre compagnie ou ville.
        static::updating(function ($model) {
            $user = auth()->user();

            if (! $user || $user->isSuperAdmin() || ! $user->compagnieId()) {
                return;
            }

            if ($model->isDirty('compagnie_id')) {
                $model->compagnie_id = $model->getOriginal('compagnie_id');
            }

            if ($user->villeId() && in_array('ville_id', $model->getFillable(), true) && $model->isDirty('ville_id')) {
                $model->ville_id = $model->getOriginal('ville_id');
            }
        });
    }
}