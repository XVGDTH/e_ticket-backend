<?php

namespace App\Services\Personnel;

use App\Models\BulletinPaie;
use App\Models\PeriodePaie;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Exports CSV (séparateur « ; », UTF-8 avec BOM) lisibles directement dans Excel. */
class Exports
{
    private static function flux(string $nom, array $entete, iterable $lignes): StreamedResponse
    {
        return response()->streamDownload(function () use ($entete, $lignes) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, $entete, ';');

            foreach ($lignes as $l) {
                fputcsv($f, $l, ';');
            }

            fclose($f);
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function livrePaie(PeriodePaie $p, User $u): StreamedResponse
    {
        $lignes = BulletinPaie::with('employe')->visiblePour($u)->where('periode_paie_id', $p->id)->get()
            ->sortBy(fn ($b) => $b->employe->nom)
            ->map(fn (BulletinPaie $b) => [
                $b->employe->matricule, $b->employe->nomComplet(), $b->employe->poste,
                $b->salaire_base, $b->retenue_absences, $b->primes_fixes, $b->heures_sup_montant, $b->primes_variables,
                $b->brut, $b->cnss_salarie, $b->iuts, $b->retenue_avance, $b->autres_retenues, $b->net_a_payer,
                $b->cnss_employeur, $b->tpa, $b->cout_employeur, $b->statut_paiement === 'paye' ? 'Payé' : 'À payer',
            ]);

        return self::flux(
            'livre-de-paie-'.$p->annee.'-'.str_pad((string) $p->mois, 2, '0', STR_PAD_LEFT).'.csv',
            ['Matricule', 'Employé', 'Poste', 'Salaire de base', 'Retenue absences', 'Indemnités fixes', 'Heures sup.', 'Primes variables',
                'Brut', 'CNSS salarié', 'IUTS', 'Avances', 'Autres retenues', 'Net à payer', 'CNSS employeur', 'TPA', 'Coût employeur', 'Paiement'],
            $lignes
        );
    }

    public static function etatCnss(PeriodePaie $p, User $u): StreamedResponse
    {
        $lignes = BulletinPaie::with('employe')->visiblePour($u)->where('periode_paie_id', $p->id)->get()
            ->sortBy(fn ($b) => $b->employe->nom)
            ->map(fn (BulletinPaie $b) => [
                $b->employe->cnss_numero ?: 'NON RENSEIGNÉ', $b->employe->nomComplet(),
                $b->base_cnss, $b->cnss_salarie, $b->cnss_employeur, $b->cnss_salarie + $b->cnss_employeur,
            ]);

        return self::flux(
            'etat-cnss-'.$p->annee.'-'.str_pad((string) $p->mois, 2, '0', STR_PAD_LEFT).'.csv',
            ['N° CNSS', 'Employé', 'Base cotisable', 'Part salarié', 'Part employeur', 'Total à verser'],
            $lignes
        );
    }
}
