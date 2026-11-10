@php
    $f = fn ($v) => number_format((int) $v, 0, ',', ' ');
    $d = $b->detail ?? [];
    $enLettres = null;
    if (extension_loaded('intl')) {
        $enLettres = (new \NumberFormatter('fr', \NumberFormatter::SPELLOUT))->format((int) $b->net_a_payer);
    }
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Bulletin de paie — {{ $e->nomComplet() }} — {{ $b->periode->libelle() }}</title>
<style>
    body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #111; margin: 24px auto; max-width: 820px; }
    h1 { font-size: 18px; margin: 0; } h2 { font-size: 13px; margin: 18px 0 6px; text-transform: uppercase; letter-spacing: .04em; }
    .entete { display: flex; justify-content: space-between; border-bottom: 2px solid #111; padding-bottom: 10px; }
    .grille { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; margin-top: 10px; }
    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    th, td { border: 1px solid #999; padding: 5px 8px; } th { background: #eee; text-align: left; }
    td.n, th.n { text-align: right; white-space: nowrap; }
    .net { font-size: 15px; font-weight: bold; background: #f3f3f3; }
    .signatures { display: flex; justify-content: space-between; margin-top: 40px; }
    .signatures div { width: 45%; border-top: 1px solid #111; padding-top: 4px; text-align: center; }
    .alerte { color: #a00; margin-top: 6px; }
    .actions { text-align: right; margin-bottom: 10px; } button { padding: 6px 14px; }
    @media print { .actions { display: none; } body { margin: 0; } }
</style>
</head>
<body>
<div class="actions"><button onclick="window.print()">Imprimer / enregistrer en PDF</button></div>

<div class="entete">
    <div><h1>{{ $b->compagnie->nom }}</h1><div>{{ $e->gare ? $e->gare->quartier . ' — ' . ($e->gare->ville->nom ?? '') : 'Siège' }}</div></div>
    <div style="text-align:right"><h1>BULLETIN DE PAIE</h1><div>{{ $b->periode->libelle() }}</div></div>
</div>

<div class="grille">
    <div><strong>Employé :</strong> {{ $e->nomComplet() }}</div>
    <div><strong>Matricule :</strong> {{ $e->matricule }}</div>
    <div><strong>Poste :</strong> {{ $e->poste }}</div>
    <div><strong>N° CNSS :</strong> {{ $e->cnss_numero ?: '—' }}</div>
    <div><strong>Date d'embauche :</strong> {{ $e->date_embauche->format('d/m/Y') }}</div>
    <div><strong>Charges de famille retenues :</strong> {{ $d['charges_famille'] ?? $e->chargesFamille() }}</div>
    <div><strong>Jours travaillés :</strong> {{ $b->jours_travailles }}</div>
    <div><strong>Jours non payés :</strong> {{ $b->jours_non_payes }}</div>
</div>

<h2>Gains</h2>
<table>
    <tr><th>Libellé</th><th class="n">Montant (FCFA)</th></tr>
    <tr><td>{{ ($d['mode'] ?? 'mensuel') === 'journalier' ? 'Salaire (' . $b->jours_travailles . ' j × ' . $f($d['taux_journalier'] ?? 0) . ')' : 'Salaire de base' }}</td><td class="n">{{ $f($b->salaire_base) }}</td></tr>
    @if($b->retenue_absences > 0)<tr><td>Retenue pour absences ({{ $b->jours_non_payes }} j)</td><td class="n">− {{ $f($b->retenue_absences) }}</td></tr>@endif
    @foreach(($d['elements'] ?? []) as $el)<tr><td>{{ $el['libelle'] }}</td><td class="n">{{ $f($el['montant']) }}</td></tr>@endforeach
    @if($b->heures_sup_montant > 0)<tr><td>Heures supplémentaires ({{ floor($b->heures_sup_minutes / 60) }} h {{ $b->heures_sup_minutes % 60 }} min)</td><td class="n">{{ $f($b->heures_sup_montant) }}</td></tr>@endif
    @if($b->primes_variables > 0)<tr><td>Primes du mois</td><td class="n">{{ $f($b->primes_variables) }}</td></tr>@endif
    <tr><th>Salaire brut</th><th class="n">{{ $f($b->brut) }}</th></tr>
</table>

<h2>Retenues</h2>
<table>
    <tr><th>Libellé</th><th class="n">Base</th><th class="n">Montant (FCFA)</th></tr>
    <tr><td>Cotisation CNSS salarié ({{ config('personnel.cnss.salarie_pct') }} %)</td><td class="n">{{ $f($b->base_cnss) }}</td><td class="n">{{ $f($b->cnss_salarie) }}</td></tr>
    <tr><td>IUTS</td><td class="n">{{ $f($b->brut_imposable) }}</td><td class="n">{{ $f($b->iuts) }}</td></tr>
    @if($b->retenue_avance > 0)<tr><td>Remboursement avance / prêt</td><td></td><td class="n">{{ $f($b->retenue_avance) }}</td></tr>@endif
    @if($b->autres_retenues > 0)<tr><td>Autres retenues</td><td></td><td class="n">{{ $f($b->autres_retenues) }}</td></tr>@endif
    <tr class="net"><td colspan="2">NET À PAYER</td><td class="n">{{ $f($b->net_a_payer) }}</td></tr>
</table>
@if($enLettres)<p>Arrêté le présent bulletin à la somme de <strong>{{ $enLettres }} francs CFA</strong>.</p>@endif
@foreach(($d['alertes'] ?? []) as $al)<div class="alerte">⚠ {{ $al }}</div>@endforeach

<p>Charges patronales (information) : CNSS {{ $f($b->cnss_employeur) }} FCFA — TPA {{ $f($b->tpa) }} FCFA.<br>
Mode de paiement : {{ \App\Models\Employe::MODES_PAIEMENT[$e->mode_paiement] ?? $e->mode_paiement }}@if($e->numero_paiement) ({{ $e->numero_paiement }})@endif
@if($b->statut_paiement === 'paye') — payé le {{ $b->paye_le?->format('d/m/Y') }}@endif</p>

<div class="signatures"><div>L'employeur</div><div>L'employé (lu et approuvé)</div></div>
</body>
</html>
