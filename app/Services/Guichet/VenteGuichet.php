<?php

namespace App\Services\Guichet;

use App\Enums\BilletStatut;
use App\Models\Billet;
use App\Models\Caisse;
use App\Models\Gare;
use App\Models\MouvementCaisse;
use App\Models\ReportBillet;
use App\Models\RouteTransport;
use App\Models\User;
use App\Models\VoyageCar;
use App\Services\Billetterie;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Toute la logique de vente au guichet et de caisse. Les écrans n'ont rien à décider eux-mêmes. */
class VenteGuichet
{
    // ------------------------------------------------------------------ caisse

    public static function caisseOuverte(User $u): ?Caisse
    {
        return Caisse::query()->where('user_id', $u->id)->where('statut', 'ouverte')->latest('ouverte_le')->first();
    }

    /** @return array<int,string> gares où l'utilisateur peut tenir une caisse */
    public static function garesPour(User $u): array
    {
        $q = match (true) {
            $u->isSuperAdmin() => Gare::query(),
            $u->isAdminCompagnie() => $u->garesGerables(),
            $u->isAgentGare() => Gare::query()->whereKey($u->gareId() ?? 0),
            default => Gare::query()->whereRaw('1 = 0'),
        };

        return $q->with('ville')->orderBy('quartier')->get()
            ->mapWithKeys(fn (Gare $g) => [$g->id => $g->quartier.($g->ville ? ' — '.$g->ville->nom : '')])->all();
    }

    public static function ouvrir(User $u, int $gareId, int $fond): Caisse
    {
        abort_unless(AccesGuichet::visible($u), 403);
        abort_unless(array_key_exists($gareId, self::garesPour($u)), 403);

        return DB::transaction(function () use ($u, $gareId, $fond) {
            User::query()->whereKey($u->id)->lockForUpdate()->first();

            if (self::caisseOuverte($u)) {
                throw new \DomainException('Vous avez déjà une caisse ouverte : fermez-la avant d\'en ouvrir une autre.');
            }

            $gare = Gare::findOrFail($gareId);

            return Caisse::create([
                'compagnie_id' => $gare->compagnie_id, 'gare_id' => $gare->id, 'user_id' => $u->id,
                'ouverte_le' => now(), 'fond_initial' => max(0, $fond), 'statut' => 'ouverte',
            ]);
        });
    }

    /** Un agent ferme sa caisse ; un gérant de la zone peut fermer celle d'un agent. */
    public static function fermer(Caisse $c, User $u, int $compte, ?string $note): Caisse
    {
        return DB::transaction(function () use ($c, $u, $compte, $note) {
            $c = Caisse::query()->whereKey($c->id)->lockForUpdate()->firstOrFail();

            abort_unless(Caisse::query()->visiblePour($u)->whereKey($c->id)->exists(), 403);
            abort_unless((int) $c->user_id === (int) $u->id || AccesGuichet::gerant($u), 403);

            if ($c->statut !== 'ouverte') {
                throw new \DomainException('Cette caisse est déjà fermée.');
            }

            $attendu = $c->especesAttendues();
            $ecart = $compte - $attendu;

            if (abs($ecart) > (int) config('guichet.ecart_tolerance', 0) && mb_strlen(trim((string) $note)) < 5) {
                throw new \DomainException('Il y a un écart de caisse : expliquez-le dans la note (5 caractères minimum).');
            }

            $c->forceFill([
                'statut' => 'fermee', 'fermee_le' => now(), 'especes_attendues' => $attendu,
                'especes_comptees' => $compte, 'ecart' => $ecart, 'note' => $note ? trim($note) : null,
            ])->save();

            return $c;
        });
    }

    public static function valider(Caisse $c, User $u): Caisse
    {
        abort_unless(AccesGuichet::gerant($u), 403);
        abort_unless(Caisse::query()->visiblePour($u)->whereKey($c->id)->exists(), 403);

        if ($c->statut !== 'fermee') {
            throw new \DomainException('Seule une caisse fermée peut être validée.');
        }

        $c->forceFill(['statut' => 'validee', 'valide_par' => $u->id, 'valide_le' => now()])->save();

        return $c;
    }

    /** Entrée ou sortie d'espèces hors vente : versement au siège, dépense, apport de monnaie. */
    public static function mouvementManuel(Caisse $c, User $u, string $sens, int $montant, string $motif): MouvementCaisse
    {
        abort_unless(Caisse::query()->visiblePour($u)->whereKey($c->id)->exists(), 403);
        abort_unless((int) $c->user_id === (int) $u->id || AccesGuichet::gerant($u), 403);

        if ($c->statut !== 'ouverte') {
            throw new \DomainException('La caisse est fermée.');
        }

        if ($montant < 1 || mb_strlen(trim($motif)) < 3) {
            throw new \DomainException('Indiquez un montant et un motif.');
        }

        if ($sens === 'sortie' && $montant > $c->especesAttendues()) {
            throw new \DomainException('Il n\'y a pas assez d\'espèces en caisse pour cette sortie.');
        }

        return MouvementCaisse::create([
            'caisse_id' => $c->id, 'compagnie_id' => $c->compagnie_id, 'gare_id' => $c->gare_id, 'user_id' => $u->id,
            'type' => $sens === 'sortie' ? 'sortie' : 'entree', 'mode' => 'especes',
            'montant' => $sens === 'sortie' ? -$montant : $montant, 'motif' => trim($motif),
        ]);
    }

    // ------------------------------------------------------------------ places et départs

    /** Places que le guichet peut encore vendre sur un départ (cars de la compagnie moins le quota de l'application). */
    public static function placesRestantes(RouteTransport $route, string $date, string $heure): int
    {
        return max(0, Billetterie::placesGuichet($route) - (int) Billetterie::vendus($route->id, $date, 'guichet')->get($heure, 0));
    }

    /** @return array<string,string> « HH:MM » => libellé avec les places restantes ; les départs fermés à la vente sont exclus */
    public static function departs(RouteTransport $route, string $date): array
    {
        $route->loadMissing('horaires');
        $out = [];

        foreach (Billetterie::horairesDuJour($route, $date) as $h) {
            $ferme = Carbon::parse($date)->setTimeFromTimeString($h)->subMinutes((int) config('guichet.vente_fermee_minutes_avant', 0));

            if (now()->gte($ferme)) {
                continue;
            }

            $out[$h] = str_replace(':', 'h', $h).' — '.self::placesRestantes($route, $date, $h).' place(s)';
        }

        return $out;
    }

    // ------------------------------------------------------------------ vente

    /**
     * Vend un billet au guichet.
     *
     * @param  array{route_transport_id:int,date_voyage:string,heure:string,voyageurs:array,remise?:int,mode:string,montant_recu?:int,reference_paiement?:string,nom_client?:string,telephone_client?:string}  $d
     *
     * @throws \DomainException message affichable à l'agent
     */
    public static function vendre(User $u, array $d): Billet
    {
        abort_unless(AccesGuichet::visible($u), 403);

        $caisse = self::caisseOuverte($u);

        if (! $caisse) {
            throw new \DomainException('Ouvrez votre caisse avant de vendre.');
        }

        $noms = collect($d['voyageurs'] ?? [])->map(fn ($v) => trim((string) ($v['nom'] ?? '')))->values();
        $n = $noms->count();
        $max = (int) config('billetterie.voyageurs_max', 10);

        if ($n < 1 || $n > $max) {
            throw new \DomainException('Le nombre de voyageurs doit être compris entre 1 et '.$max.'.');
        }

        if (config('guichet.nom_obligatoire') && $noms->contains(fn ($x) => mb_strlen($x) < 2)) {
            throw new \DomainException('Saisissez le nom de chaque voyageur.');
        }

        $noms = $noms->map(fn ($x, $i) => $x !== '' ? $x : 'Passager '.($i + 1));

        $modes = config('guichet.modes', []);
        $mode = (string) ($d['mode'] ?? '');

        if (! isset($modes[$mode])) {
            throw new \DomainException('Choisissez un mode de paiement.');
        }

        $referencePaiement = trim((string) ($d['reference_paiement'] ?? ''));

        if (in_array($mode, config('guichet.modes_avec_reference', []), true) && mb_strlen($referencePaiement) < 4) {
            throw new \DomainException('Saisissez le numéro de la transaction '.$modes[$mode].'.');
        }

        return DB::transaction(function () use ($u, $d, $caisse, $noms, $n, $mode, $modes, $referencePaiement) {
            $route = RouteTransport::query()->whereKey($d['route_transport_id'])->lockForUpdate()->with(['compagnie', 'gareDepart', 'horaires'])->firstOrFail();

            if (! $route->actif || ! $route->compagnie?->actif) {
                throw new \DomainException('Cette route n\'est pas disponible.');
            }

            if ((int) $route->gare_depart_id !== (int) $caisse->gare_id) {
                throw new \DomainException('Cette route ne part pas de la gare de votre caisse.');
            }

            $date = Carbon::parse($d['date_voyage'])->startOfDay();

            if ($date->lt(today()) || $date->gt(today()->addDays((int) config('guichet.jours_vente_max', 30)))) {
                throw new \DomainException('Date de voyage hors des ventes autorisées.');
            }

            $heure = Billetterie::normaliserHeure((string) $d['heure']);

            if (! in_array($heure, Billetterie::horairesDuJour($route, $date->toDateString()), true)) {
                throw new \DomainException('Aucun départ à cette heure ce jour-là.');
            }

            $fermeture = $date->copy()->setTimeFromTimeString($heure)->subMinutes((int) config('guichet.vente_fermee_minutes_avant', 0));

            if (now()->gte($fermeture)) {
                throw new \DomainException('Les ventes de ce départ sont fermées.');
            }

            $reste = self::placesRestantes($route, $date->toDateString(), $heure);

            if ($n > $reste) {
                throw new \DomainException('Il ne reste que '.$reste.' place(s) au guichet sur ce départ.');
            }

            $brut = (int) $route->prix * $n;
            $remise = max(0, (int) ($d['remise'] ?? 0));
            $plafond = AccesGuichet::gerant($u) ? $brut : (int) floor($brut * (float) config('guichet.remise_max_pct_agent', 10) / 100);

            if ($remise > $plafond) {
                throw new \DomainException('Remise trop élevée : '.number_format($plafond, 0, ',', ' ').' FCFA au maximum pour vous.');
            }

            $net = $brut - $remise;

            if ($net < 1) {
                throw new \DomainException('Le montant à encaisser doit être supérieur à zéro.');
            }

            if ($mode === 'especes' && (int) ($d['montant_recu'] ?? 0) < $net) {
                throw new \DomainException('Le montant reçu est inférieur au montant à payer.');
            }

            do {
                $reference = strtoupper(Str::random(4)).'-'.random_int(100000, 999999);
            } while (Billet::where('reference', $reference)->exists());

            // Mêmes codes passagers que l'application : le contrôle à l'embarquement est identique.
            $voyageurs = $noms->values()->map(fn ($nom, $i) => [
                'nom' => $nom,
                'code' => strtoupper(substr(hash_hmac('sha256', "$reference|$i|$nom", (string) config('app.key')), 0, 8)),
            ])->all();

            $billet = Billet::create([
                'reference' => $reference,
                'user_id' => null,
                'route_transport_id' => $route->id,
                'gare_id' => $route->gare_depart_id,
                'date_voyage' => $date->toDateString(),
                'heure' => $heure,
                'travelers_count' => $n,
                'montant_transport' => $net,
                'taxe' => 0,
                'operateur_paiement' => $modes[$mode],
                'voyageurs' => $voyageurs,
                'date_paiement' => now(),
                'validite_jours' => (int) ($route->compagnie?->validite_jours ?: config('billetterie.validite_jours', 12)),
                'canal' => 'guichet',
                'vendu_par' => $u->id,
                'caisse_id' => $caisse->id,
                'remise' => $remise,
                'nom_client' => filled($d['nom_client'] ?? null) ? trim($d['nom_client']) : null,
                'telephone_client' => filled($d['telephone_client'] ?? null) ? trim($d['telephone_client']) : null,
                'reference_paiement' => $referencePaiement ?: null,
            ]);

            MouvementCaisse::create([
                'caisse_id' => $caisse->id, 'compagnie_id' => $caisse->compagnie_id, 'gare_id' => $caisse->gare_id,
                'billet_id' => $billet->id, 'user_id' => $u->id, 'type' => 'vente', 'mode' => $mode,
                'montant' => $net, 'reference' => $referencePaiement ?: null,
            ]);

            return $billet;
        });
    }

    // ------------------------------------------------------------------ annulation et report

    /**
     * Annule (erreur de saisie dans la caisse en cours) ou rembourse (plus tard) un billet vendu au guichet.
     * Un agent annule seulement ses ventes de la caisse en cours, dans le délai prévu et avant le départ.
     *
     * @throws \DomainException
     */
    public static function annuler(Billet $b, User $u, string $motif, bool $restituer): Billet
    {
        abort_unless(AccesGuichet::visible($u), 403);

        return DB::transaction(function () use ($b, $u, $motif, $restituer) {
            $b = Billet::query()->whereKey($b->id)->lockForUpdate()->firstOrFail();

            if ($b->canal !== 'guichet') {
                throw new \DomainException('Ce billet n\'a pas été vendu au guichet.');
            }

            if ($b->statut !== BilletStatut::Valide) {
                throw new \DomainException('Seul un billet valide peut être annulé.');
            }

            $origine = $b->caisse_id ? Caisse::find($b->caisse_id) : null;
            $memeSession = $origine && $origine->statut === 'ouverte';

            if (! AccesGuichet::gerant($u)) {
                $depart = $b->date_voyage->copy()->setTimeFromTimeString(Billetterie::normaliserHeure((string) $b->heure));

                if ((int) $b->vendu_par !== (int) $u->id || ! $memeSession || (int) $origine->user_id !== (int) $u->id) {
                    throw new \DomainException('Vous ne pouvez annuler que vos ventes de la caisse en cours. Demandez à un gérant.');
                }

                if ($b->created_at->lt(now()->subMinutes((int) config('guichet.delai_annulation_agent_minutes', 30)))) {
                    throw new \DomainException('Le délai d\'annulation est dépassé : demandez à un gérant.');
                }

                if ($depart->isPast()) {
                    throw new \DomainException('Le départ est passé : demandez à un gérant.');
                }
            }

            $b->changerStatut($memeSession ? BilletStatut::Annule : BilletStatut::Rembourse, $u, $motif, 'guichet');

            if ($restituer) {
                $caisse = ($memeSession && (int) $origine->user_id === (int) $u->id) ? $origine : self::caisseOuverte($u);

                if (! $caisse) {
                    throw new \DomainException('Ouvrez une caisse pour rembourser le client, ou décochez « Restituer l\'argent ».');
                }

                $mode = MouvementCaisse::where('billet_id', $b->id)->where('type', 'vente')->value('mode') ?? 'especes';

                MouvementCaisse::create([
                    'caisse_id' => $caisse->id, 'compagnie_id' => $caisse->compagnie_id, 'gare_id' => $caisse->gare_id,
                    'billet_id' => $b->id, 'user_id' => $u->id, 'type' => $memeSession ? 'annulation' : 'remboursement',
                    'mode' => $mode, 'montant' => -(int) $b->montant_transport, 'motif' => trim($motif),
                ]);
            }

            return $b->fresh();
        });
    }

    /** Change le départ d'un billet vendu au guichet, dans sa durée de validité. */
    public static function reporter(Billet $b, User $u, Carbon $nouvelleDate, string $nouvelleHeure): Billet
    {
        abort_unless(AccesGuichet::visible($u), 403);

        return DB::transaction(function () use ($b, $u, $nouvelleDate, $nouvelleHeure) {
            RouteTransport::query()->whereKey($b->route_transport_id)->lockForUpdate()->first();
            $b = Billet::query()->visiblesPar($u)->whereKey($b->id)->lockForUpdate()->firstOrFail();

            if ($b->canal !== 'guichet' || $b->statut !== BilletStatut::Valide) {
                throw new \DomainException('Seul un billet valide vendu au guichet peut être reporté ici.');
            }

            $heure = Billetterie::normaliserHeure($nouvelleHeure);
            $date = $nouvelleDate->copy()->startOfDay();
            $fin = $b->valide_jusqu_au;

            if (! $fin || $date->lt(today()) || $date->gt($fin)) {
                throw new \DomainException('Choisissez un jour entre aujourd\'hui et le '.($fin ? $fin->format('d/m/Y') : '—').' (fin de validité).');
            }

            if ($date->copy()->setTimeFromTimeString($heure)->lte(now())) {
                throw new \DomainException('Ce départ est déjà passé.');
            }

            $ancienneHeure = Billetterie::normaliserHeure((string) $b->heure);

            if ($b->date_voyage->copy()->setTimeFromTimeString($ancienneHeure)->isPast() && ! AccesGuichet::gerant($u)) {
                throw new \DomainException('Le départ initial est passé : seul un gérant peut reporter ce billet.');
            }

            if ($date->equalTo($b->date_voyage) && $heure === $ancienneHeure) {
                throw new \DomainException('Choisissez un autre départ que celui du billet.');
            }

            $route = RouteTransport::with(['horaires', 'compagnie', 'gareDepart'])->findOrFail($b->route_transport_id);

            if (! in_array($heure, Billetterie::horairesDuJour($route, $date->toDateString()), true)) {
                throw new \DomainException('Aucun départ à cette heure ce jour-là.');
            }

            $reste = self::placesRestantes($route, $date->toDateString(), $heure);

            if ($b->travelers_count > $reste) {
                throw new \DomainException('Il ne reste que '.$reste.' place(s) au guichet sur ce départ.');
            }

            $ancienneDate = $b->date_voyage->toDateString();

            $b->date_voyage = $date;
            $b->heure = $heure;
            $b->save();

            ReportBillet::create([
                'billet_id' => $b->id, 'route_transport_id' => $b->route_transport_id,
                'ancienne_date' => $ancienneDate, 'ancienne_heure' => $ancienneHeure,
                'nouvelle_date' => $date->toDateString(), 'nouvelle_heure' => $heure,
            ]);

            return $b;
        });
    }

    // ------------------------------------------------------------------ liaison avec la flotte

    /** Places et recettes du guichet sur un départ (billets valides ou utilisés). */
    public static function totauxDepart(int $routeId, string $date, string $heure): array
    {
        $billets = Billet::query()->where('canal', 'guichet')->where('route_transport_id', $routeId)
            ->whereDate('date_voyage', $date)->whereIn('statut', BilletStatut::comptabilises())
            ->get(['heure', 'travelers_count', 'montant_transport'])
            ->filter(fn ($b) => Billetterie::normaliserHeure((string) $b->heure) === $heure);

        return ['places' => (int) $billets->sum('travelers_count'), 'montant' => (int) $billets->sum('montant_transport')];
    }

    /** Part du guichet qui revient à ce car quand plusieurs cars font le même départ (au prorata des places). */
    public static function partVoyage(VoyageCar $v): array
    {
        $t = self::totauxDepart((int) $v->route_transport_id, $v->date_voyage->toDateString(), (string) $v->heure);

        $cars = VoyageCar::query()->with('vehicule')->where('route_transport_id', $v->route_transport_id)
            ->whereDate('date_voyage', $v->date_voyage)->where('heure', $v->heure)->where('statut', '!=', 'annule')->get();

        $total = max(1, (int) $cars->sum(fn ($c) => (int) $c->vehicule?->places));
        $part = ((int) $v->vehicule?->places) / $total;

        return ['places' => (int) round($t['places'] * $part), 'montant' => (int) round($t['montant'] * $part)];
    }
}
