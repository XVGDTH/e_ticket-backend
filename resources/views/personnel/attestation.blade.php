@php
    $sorti = $e->statut === 'sorti' && $e->date_sortie;
    $genre = $e->sexe === 'F' ? ['Madame', 'née', 'employée'] : ['Monsieur', 'né', 'employé'];
    $contrat = $e->contrats()->orderBy('date_debut')->first();
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Attestation de travail — {{ $e->nomComplet() }}</title>
<style>
    body { font-family: "Times New Roman", serif; font-size: 15px; line-height: 1.7; max-width: 740px; margin: 40px auto; color: #111; }
    h1 { text-align: center; text-decoration: underline; margin: 40px 0 30px; font-size: 20px; letter-spacing: .05em; }
    .actions { text-align: right; } button { padding: 6px 14px; }
    .sig { margin-top: 60px; text-align: right; }
    @media print { .actions { display: none; } }
</style>
</head>
<body>
<div class="actions"><button onclick="window.print()">Imprimer / enregistrer en PDF</button></div>
<strong>{{ $e->compagnie->nom }}</strong><br>
{{ $e->gare ? $e->gare->quartier . ' — ' . ($e->gare->ville->nom ?? '') : 'Siège' }}

<h1>{{ $sorti ? 'CERTIFICAT DE TRAVAIL' : 'ATTESTATION DE TRAVAIL' }}</h1>

<p>Je soussigné(e), représentant la compagnie {{ $e->compagnie->nom }}, atteste que
{{ $genre[0] }} <strong>{{ $e->nomComplet() }}</strong>@if($e->date_naissance), {{ $genre[1] }}(e) le {{ $e->date_naissance->format('d/m/Y') }}@if($e->lieu_naissance) à {{ $e->lieu_naissance }}@endif @endif
@if($e->cnib_numero), titulaire de la pièce d'identité n° {{ $e->cnib_numero }}, @endif
@if($sorti)
a été {{ $genre[2] }}(e) au sein de notre compagnie du <strong>{{ $e->date_embauche->format('d/m/Y') }}</strong> au <strong>{{ $e->date_sortie->format('d/m/Y') }}</strong>, en qualité de <strong>{{ $e->poste }}</strong>.
@else
est {{ $genre[2] }}(e) au sein de notre compagnie depuis le <strong>{{ $e->date_embauche->format('d/m/Y') }}</strong>, en qualité de <strong>{{ $e->poste }}</strong>@if($contrat), sous contrat {{ \App\Models\ContratEmploye::TYPES[$contrat->type] ?? $contrat->type }}@endif.
@endif
</p>
@if($sorti)<p>L'intéressé(e) nous quitte libre de tout engagement, sous réserve de l'apurement de ses éventuelles dettes envers la compagnie.</p>@endif
<p>La présente attestation est délivrée pour servir et valoir ce que de droit.</p>

<div class="sig">Fait à {{ $e->gare?->ville?->nom ?? 'Ouagadougou' }}, le {{ today()->format('d/m/Y') }}<br><br>Le représentant de la compagnie<br><br><br>(signature et cachet)</div>
</body>
</html>
