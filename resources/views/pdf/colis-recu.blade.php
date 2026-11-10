<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 22px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0f172a; }
    .tete { background: #b45309; color: #fff; padding: 14px 16px; border-radius: 10px; }
    .tete .c { font-size: 9px; letter-spacing: .15em; text-transform: uppercase; opacity: .9; }
    .tete .t { font-size: 17px; font-weight: bold; margin-top: 3px; }
    .ref { text-align: center; font-size: 22px; font-weight: bold; letter-spacing: .08em; margin: 14px 0 4px; color: #b45309; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    td { padding: 6px 4px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
    td.k { color: #64748b; width: 38%; }
    .code { margin-top: 14px; padding: 12px; border: 2px dashed #b45309; border-radius: 10px; text-align: center; }
    .code .v { font-size: 24px; font-weight: bold; letter-spacing: .3em; }
    .note { margin-top: 6px; font-size: 8.5px; color: #64748b; text-align: center; }
    .coupe { margin: 18px 0 10px; border: 0; border-top: 2px dashed #94a3b8; }
    .etiquette { border: 2px solid #0f172a; border-radius: 10px; padding: 12px; }
    .etiquette .g { font-size: 15px; font-weight: bold; }
</style>
</head>
<body>
    <div class="tete">
        <div class="c">{{ $c->compagnie?->nom }}</div>
        <div class="t">{{ $c->type === 'courrier' ? 'Reçu de courrier' : 'Reçu de colis' }}</div>
    </div>

    <div class="ref">{{ $c->reference }}</div>

    <table>
        <tr><td class="k">Départ</td><td>{{ $c->origine?->quartier }} ({{ $c->origine?->ville?->nom }})</td></tr>
        <tr><td class="k">Arrivée</td><td>{{ $c->destination?->quartier }} ({{ $c->destination?->ville?->nom }})</td></tr>
        <tr><td class="k">Expéditeur</td><td>{{ $c->expediteur_nom }} · {{ $c->expediteur_telephone }}</td></tr>
        <tr><td class="k">Destinataire</td><td>{{ $c->destinataire_nom }} · {{ $c->destinataire_telephone }}</td></tr>
        <tr><td class="k">Contenu</td><td>{{ $c->nombre_pieces }} pièce(s){{ $c->poids_kg ? ' · ' . $c->poids_kg . ' kg' : '' }}{{ $c->description ? ' · ' . $c->description : '' }}</td></tr>
        <tr><td class="k">Frais d'envoi</td><td>{{ number_format($c->montant, 0, ',', ' ') }} FCFA · {{ $c->paiement === 'destinataire' ? 'à payer à l\'arrivée' : 'payés au départ' }}</td></tr>
        <tr><td class="k">Enregistré le</td><td>{{ $c->created_at->format('d/m/Y à H:i') }}@if ($c->createur) par {{ $c->createur->prenom }} {{ $c->createur->nom }}@endif</td></tr>
    </table>

    @if ($avecCode)
        <div class="code">
            <div>Code de retrait</div>
            <div class="v">{{ $c->code_retrait }}</div>
        </div>
        <div class="note">À communiquer au destinataire : il le présentera à la gare d'arrivée avec une pièce d'identité.</div>
    @endif

    <hr class="coupe">

    <div class="etiquette">
        <div>{{ $c->compagnie?->nom }} · {{ $c->type === 'courrier' ? 'COURRIER' : 'COLIS' }} · {{ $c->nombre_pieces }} pièce(s)</div>
        <div class="g">{{ $c->origine?->quartier }} → {{ $c->destination?->quartier }}</div>
        <div>Destinataire : {{ $c->destinataire_nom }} · {{ $c->destinataire_telephone }}</div>
        <div style="margin-top:6px;font-weight:bold">{{ $c->reference }}</div>
    </div>
</body>
</html>