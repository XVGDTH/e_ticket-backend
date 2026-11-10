<?php

namespace App\Services;

use App\Models\CptEcriture;
use App\Models\CptExercice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Attribue à chaque facture :
 *  - un numéro propre, séquentiel par compagnie et par exercice (ex. FAC-2026-00042)
 *  - un code plateforme kmaa aléatoire et unique (ex. KMAA-7QX4-M9TD)
 */
class NumerotationFactures
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sans I, O, 0, 1 : lisible à l'oral

    public static function prochainNumero(int $compagnieId, int $exerciceId, int $annee): string
    {
        return DB::transaction(function () use ($compagnieId, $exerciceId, $annee) {
            $exercice = CptExercice::query()
                ->where('compagnie_id', $compagnieId)
                ->whereKey($exerciceId)
                ->lockForUpdate()
                ->firstOrFail();

            $n = (int) $exercice->dernier_numero_facture + 1;
            $exercice->forceFill(['dernier_numero_facture' => $n])->save();

            return sprintf('FAC-%d-%05d', $annee, $n);
        });
    }

    public static function genererCodeKmaa(): string
    {
        do {
            $brut = '';
            for ($i = 0; $i < 8; $i++) {
                $brut .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $code = 'KMAA-'.substr($brut, 0, 4).'-'.substr($brut, 4, 4);
        } while (CptEcriture::query()->where('code_kmaa', $code)->exists());

        return $code;
    }

    /** À appeler à la création d'une écriture de type facture. Idempotent. */
    public static function attribuer(CptEcriture $ecriture): CptEcriture
    {
        if ($ecriture->numero_facture && $ecriture->code_kmaa) {
            return $ecriture;
        }

        $ecriture->forceFill([
            'numero_facture' => $ecriture->numero_facture
                ?: self::prochainNumero(
                    (int) $ecriture->compagnie_id,
                    (int) $ecriture->exercice_id,
                    (int) $ecriture->date_ecriture->format('Y'),
                ),
            'code_kmaa' => $ecriture->code_kmaa ?: self::genererCodeKmaa(),
        ])->save();

        return $ecriture;
    }
}
