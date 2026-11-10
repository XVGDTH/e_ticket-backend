<?php

namespace App\Services;

use App\Enums\BilletStatut;
use App\Models\Billet;
use App\Models\ReportBillet;
use App\Models\User;
use App\Models\VerificationBillet;
use App\Models\Ville;
use App\Services\Finance\RapportFinancier;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Throwable;

class ControleBillet
{
    public const LIBELLES = [
        'valide' => 'Billet valide',
        'deja_utilise' => 'Déjà utilisé',
        'invalide' => 'Non reconnu',
        'autre_gare' => 'Autre gare ou compagnie',
        'trop_tot' => 'Pas encore le jour',
        'a_reporter' => 'Départ manqué',
        'expire' => 'Expiré',
        'annule' => 'Annulé ou remboursé',
        'non_paye' => 'Paiement non confirmé',
    ];

    /** Analyse un code lu ou saisi et, par défaut, l'enregistre dans le journal des contrôles. */
    public static function analyser(string $contenu, User $user, bool $journaliser = true): array
    {
        $lu = BilletToken::decoder($contenu);

        $billet = $lu['reference'] !== ''
            ? Billet::with(['user', 'route.compagnie', 'route.gareDepart.ville'])->where('reference', $lu['reference'])->first()
            : null;

        $resultat = self::evaluer($lu, $billet, $user);

        if ($journaliser) {
            try {
                VerificationBillet::create([
                    'billet_id' => $resultat['code'] === 'invalide' ? null : $billet?->id,
                    'user_id' => $user->id,
                    'gare_id' => $user->gareId() ?? $billet?->route?->gare_depart_id,
                    'mode' => $lu['mode'],
                    'resultat' => $resultat['code'],
                    'contenu' => $resultat['code'] === 'invalide' ? Str::limit(trim($contenu), 120, '') : null,
                ]);
            } catch (Throwable) {
                // Le journal ne doit jamais empêcher un contrôle.
            }
        }

        return $resultat;
    }

    private static function evaluer(array $lu, ?Billet $billet, User $user): array
    {
        // Code fabriqué, modifié, ou référence inconnue.
        if (! $billet || ($lu['mode'] === 'qr' && ! BilletToken::verifier($billet, (string) $lu['signature']))) {
            return self::resultat(
                $lu, 'invalide', 'danger', 'BILLET NON RECONNU',
                $lu['mode'] === 'qr'
                    ? 'Ce code n\'a pas été émis par l\'application ou a été modifié. Refusez l\'embarquement.'
                    : 'Aucun billet ne porte cette référence. Refusez l\'embarquement.'
            );
        }

        // Billet d'une autre gare ou d'une autre compagnie : on n'en dit presque rien.
        if (! self::dansLePerimetre($billet, $user)) {
            return self::resultat(
                $lu, 'autre_gare', 'danger', 'AUTRE GARE OU AUTRE COMPAGNIE',
                'Ce billet n\'est pas valable pour votre gare. Ne l\'embarquez pas.',
                $billet, self::lignes($billet, $user, false)
            );
        }

        $heure = Billetterie::normaliserHeure((string) $billet->heure);
        $depart = $billet->date_voyage->copy()->setTimeFromTimeString($heure);
        $fin = $billet->valide_jusqu_au;

        $embarque = null;

        if ($billet->statut === BilletStatut::Utilise) {
            $evenement = $billet->evenements()->where('statut_apres', BilletStatut::Utilise->value)->latest('created_at')->first();
            $agent = $evenement?->user_id ? User::find($evenement->user_id) : null;
            $embarque = Carbon::parse($evenement?->created_at ?? $billet->updated_at)->format('d/m/Y à H:i')
                .($agent ? ' par '.trim($agent->prenom.' '.$agent->nom) : '');
        }

        [$code, $niveau, $titre, $message] = match (true) {
            $billet->statut === BilletStatut::Utilise => [
                'deja_utilise', 'danger', 'DÉJÀ UTILISÉ',
                // 'Ce billet a déjà servi le ' . $billet->updated_at->format('d/m/Y à H:i')
                'Ce billet a déjà servi le '.$embarque
                    .'. Le code a peut-être été copié : vérifiez l\'identité du porteur.',
            ],
            in_array($billet->statut, [BilletStatut::Annule, BilletStatut::Rembourse], true) => [
                'annule', 'danger', 'BILLET ANNULÉ', 'Ce billet est annulé ou remboursé : il ne donne plus accès au car.',
            ],
            $billet->statut === BilletStatut::EnAttentePaiement => [
                'non_paye', 'danger', 'PAIEMENT NON CONFIRMÉ', 'Le paiement de ce billet n\'est pas confirmé.',
            ],
            $billet->date_voyage->gt(today()) => [
                'trop_tot', 'warn', 'PAS ENCORE LE JOUR',
                'Ce billet est pour le '.$billet->date_voyage->format('d/m/Y').'. Il ne peut pas embarquer aujourd\'hui.',
            ],
            $billet->date_voyage->lt(today()) && $fin && today()->gt($fin) => [
                'expire', 'danger', 'BILLET EXPIRÉ', 'La validité de ce billet s\'est terminée le '.$fin->format('d/m/Y').'.',
            ],
            $billet->date_voyage->lt(today()) => [
                'a_reporter', 'warn', 'DÉPART MANQUÉ',
                'Ce billet était pour le '.$billet->date_voyage->format('d/m/Y').'. Il reste valable'
                    .($fin ? ' jusqu\'au '.$fin->format('d/m/Y') : '').' : le client doit le reporter depuis l\'application.',
            ],
            default => self::messageValide($depart),
        };

        $resultat = self::resultat($lu, $code, $niveau, $titre, $message, $billet, self::lignes($billet, $user, true));
        $resultat['voyageurs'] = self::voyageurs($billet);
        $resultat['peut_embarquer'] = $code === 'valide' && $user->isAgentGare();

        return $resultat;
    }

    private static function messageValide(Carbon $depart): array
    {
        $ouverture = $depart->copy()->subMinutes((int) config('billetterie.embarquement_minutes', 90));

        $message = now()->lt($ouverture)
            ? 'Billet authentique. Départ à '.$depart->format('H:i').' : l\'embarquement ouvre à '.$ouverture->format('H:i').'.'
            : 'Billet authentique, valable pour le départ de '.$depart->format('H:i').'. Comparez les noms avec la pièce d\'identité.';

        return ['valide', 'ok', 'BILLET VALIDE', $message];
    }

    private static function resultat(array $lu, string $code, string $niveau, string $titre, string $message, ?Billet $billet = null, array $lignes = []): array
    {
        return [
            'code' => $code,
            'niveau' => $niveau,
            'titre' => $titre,
            'message' => $message,
            'mode' => $lu['mode'],
            'billet_id' => $billet?->id,
            'lignes' => $lignes,
            'voyageurs' => [],
            'peut_embarquer' => false,
        ];
    }

    private static function dansLePerimetre(Billet $billet, User $user): bool
    {
        $route = $billet->route;

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isAgentGare()) {
            return (int) $route?->gare_depart_id === (int) $user->gareId();
        }

        if ($user->isAdminCompagnie()) {
            if ((int) $route?->compagnie_id !== (int) $user->compagnieId()) {
                return false;
            }

            return $user->villeId() === null || (int) $route->gareDepart?->ville_id === (int) $user->villeId();
        }

        return false;
    }

    private static function voyageurs(Billet $billet): array
    {
        return collect((array) $billet->voyageurs)
            ->map(fn ($v) => is_array($v) ? trim(($v['prenom'] ?? '').' '.($v['nom'] ?? '')) : (string) $v)
            ->filter()
            ->values()
            ->all();
    }

    private static function telephone(?string $numero, User $user): string
    {
        $numero = (string) $numero;

        // L'agent n'a pas besoin du numéro complet : seul l'administrateur le voit en entier.
        if ($numero === '' || RapportFinancier_autorise($user)) {
            return $numero ?: '—';
        }

        return substr($numero, 0, 2).' •• •• '.substr($numero, -2);
    }

    private static function lignes(Billet $b, User $user, bool $complet): array
    {
        $route = $b->route;
        $gare = $route?->gareDepart;
        $arrivee = $route?->ville_arrivee_id ? Ville::whereKey($route->ville_arrivee_id)->value('nom') : null;

        $lignes = [
            ['Référence', (string) $b->reference],
            ['Compagnie', (string) ($route?->compagnie?->nom ?? '—')],
            ['Gare de départ', ($gare?->quartier ?? '—').($gare?->ville ? ' · '.$gare->ville->nom : '')],
            ['Trajet', ($gare?->quartier ?? '?').' → '.($arrivee ?? '?')],
            ['Voyage', $b->date_voyage->format('d/m/Y').' à '.substr(Billetterie::normaliserHeure((string) $b->heure), 0, 5)],
        ];

        if (! $complet) {
            return $lignes;
        }

        $lignes[] = ['Statut', RapportFinancier::libelleStatut($b->statut)];
        $lignes[] = ['Nombre de voyageurs', (string) (int) $b->travelers_count];
        $lignes[] = ['Valable jusqu\'au', $b->valide_jusqu_au?->format('d/m/Y') ?? '—'];
        $lignes[] = ['Acheteur', trim(($b->user?->prenom ?? '').' '.($b->user?->nom ?? '')) ?: '—'];
        $lignes[] = ['Téléphone', self::telephone($b->user?->telephone, $user)];

        // Les montants ne sont montrés qu'au super admin et à l'admin de compagnie.
        if (RapportFinancier_autorise($user)) {
            $prix = (int) $b->montant_transport;
            $frais = (int) $b->taxe;
            $fmt = fn (int $n) => number_format($n, 0, ',', ' ').' FCFA';

            $lignes[] = ['Prix du billet', $fmt($prix)];
            $lignes[] = ['Frais', $fmt($frais)];
            $lignes[] = ['Total payé', $fmt($prix + $frais)];
            $lignes[] = ['Opérateur', (string) ($b->operateur_paiement ?? '—')];
        }

        $lignes[] = ['Payé le', $b->date_paiement?->format('d/m/Y H:i') ?? '—'];

        if (($reports = ReportBillet::where('billet_id', $b->id)->count()) > 0) {
            $lignes[] = ['Reports effectués', (string) $reports];
        }

        if ($b->presence_le) {
            $lignes[] = ['Présence signalée', ($b->presence_verifiee ? 'À la gare (position vérifiée) à ' : 'Sans position, à ').$b->presence_le->format('H:i')];
        }

        return $lignes;
    }
}

function RapportFinancier_autorise(User $user): bool
{
    return RapportFinancier::autorise($user);
}
