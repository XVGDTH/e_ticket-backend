<?php

namespace App\Services\Guichet;

use App\Enums\BilletStatut;
use App\Models\Billet;
use App\Models\MouvementCaisse;
use App\Models\User;
use Illuminate\Support\Carbon;

class RapportGuichet
{
    /** Ventes et encaissements du guichet sur une période, dans le périmètre de l'utilisateur. */
    public static function calculer(User $u, Carbon $du, Carbon $au, ?int $gareId = null, ?int $agentId = null): array
    {
        $billets = Billet::query()->visiblesPar($u)->where('canal', 'guichet')->with(['route.villeArrivee', 'gare'])
            ->whereDate('billets.created_at', '>=', $du->toDateString())->whereDate('billets.created_at', '<=', $au->toDateString())
            ->when($gareId, fn ($q) => $q->where('billets.gare_id', $gareId))
            ->when($agentId, fn ($q) => $q->where('vendu_par', $agentId))
            ->get();

        $vendus = $billets->filter(fn ($b) => in_array($b->statut->value, BilletStatut::comptabilises(), true));
        $annules = $billets->filter(fn ($b) => in_array($b->statut, [BilletStatut::Annule, BilletStatut::Rembourse], true));

        $mouvements = MouvementCaisse::query()->visiblePour($u)
            ->whereDate('created_at', '>=', $du->toDateString())->whereDate('created_at', '<=', $au->toDateString())
            ->when($gareId, fn ($q) => $q->where('gare_id', $gareId))
            ->when($agentId, fn ($q) => $q->where('user_id', $agentId))
            ->get();

        $noms = User::query()->whereIn('id', $mouvements->pluck('user_id')->merge($billets->pluck('vendu_par'))->filter()->unique())->get()
            ->mapWithKeys(fn ($x) => [$x->id => trim($x->prenom.' '.$x->nom)]);

        $encaisse = $mouvements->whereIn('type', ['vente', 'annulation', 'remboursement']);

        return [
            'totaux' => [
                'billets' => $vendus->count(),
                'voyageurs' => (int) $vendus->sum('travelers_count'),
                'chiffre_affaires' => (int) $vendus->sum('montant_transport'),
                'remises' => (int) $vendus->sum('remise'),
                'annules' => $annules->count(),
                'montant_annule' => (int) $annules->sum('montant_transport'),
                'net_encaisse' => (int) $encaisse->sum('montant'),
            ],
            'par_mode' => $encaisse->groupBy('mode')->map(fn ($g, $mode) => [
                'mode' => config('guichet.modes')[$mode] ?? $mode, 'montant' => (int) $g->sum('montant'),
            ])->values()->sortByDesc('montant')->all(),
            'par_agent' => $vendus->groupBy('vendu_par')->map(fn ($g, $id) => [
                'agent' => $noms[$id] ?? '—', 'billets' => $g->count(), 'voyageurs' => (int) $g->sum('travelers_count'), 'montant' => (int) $g->sum('montant_transport'),
            ])->values()->sortByDesc('montant')->all(),
            'par_destination' => $vendus->groupBy('route_transport_id')->map(fn ($g) => [
                'trajet' => ($g->first()->gare?->quartier ?? '?').' → '.($g->first()->route?->villeArrivee?->nom ?? '?'),
                'billets' => $g->count(), 'voyageurs' => (int) $g->sum('travelers_count'), 'montant' => (int) $g->sum('montant_transport'),
            ])->values()->sortByDesc('montant')->all(),
            'par_jour' => $vendus->groupBy(fn ($b) => $b->created_at->format('Y-m-d'))->map(fn ($g, $j) => [
                'jour' => Carbon::parse($j)->format('d/m/Y'), 'billets' => $g->count(), 'montant' => (int) $g->sum('montant_transport'),
            ])->sortKeys()->values()->all(),
            'billets' => $billets,
        ];
    }
}
