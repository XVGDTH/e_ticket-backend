v@php $f = fn ($v) => number_format((int) $v, 0, ',', ' '); $modes = config('guichet.modes'); @endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>État de caisse #{{ $c->id }}</title>
<style>
    @page { size: A4; margin: 12mm; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #000; }
    h1 { font-size: 18px; margin: 0 0 6px; } table { width: 100%; border-collapse: collapse; margin: 8px 0; }
    th, td { border: 1px solid #888; padding: 4px 6px; text-align: left; } th { background: #eee; } td.r, th.r { text-align: right; }
    .barre { text-align: right; } .signatures { display: flex; justify-content: space-between; margin-top: 40px; }
    .signatures div { width: 40%; border-top: 1px solid #000; text-align: center; padding-top: 4px; }
    @media print { .barre { display: none; } }
</style>
</head>
<body>
<div class="barre"><button onclick="window.print()">Imprimer</button></div>
<h1>ÉTAT DE CAISSE n° {{ $c->id }}</h1>
<div>Agent : <strong>{{ $c->libelleAgent() }}</strong> — Gare : <strong>{{ $c->gare?->quartier }}</strong>
    — Ouverte le {{ $c->ouverte_le->format('d/m/Y H:i') }}
    — {{ $c->fermee_le ? 'fermée le ' . $c->fermee_le->format('d/m/Y H:i') : 'en cours' }}</div>

<table>
    <tr><th>Fond de caisse</th><td class="r">{{ $f($c->fond_initial) }} FCFA</td></tr>
    @foreach($c->totauxParMode() as $mode => $total)
        <tr><th>Encaissé — {{ $modes[$mode] ?? $mode }} (net des annulations)</th><td class="r">{{ $f($total) }} FCFA</td></tr>
    @endforeach
    <tr><th>Espèces attendues</th><td class="r"><strong>{{ $f($c->statut === 'ouverte' ? $c->especesAttendues() : $c->especes_attendues) }} FCFA</strong></td></tr>
    @if($c->statut !== 'ouverte')
        <tr><th>Espèces comptées</th><td class="r">{{ $f($c->especes_comptees) }} FCFA</td></tr>
        <tr><th>Écart</th><td class="r"><strong>{{ $f($c->ecart) }} FCFA</strong></td></tr>
    @endif
</table>
@if($c->note)<div>Note : {{ $c->note }}</div>@endif

<h3>Détail des mouvements</h3>
<table>
    <tr><th>Heure</th><th>Opération</th><th>Billet</th><th>Mode</th><th class="r">Montant</th><th>Motif</th></tr>
    @foreach($c->mouvements->sortBy('created_at') as $m)
        <tr>
            <td>{{ $m->created_at->format('H:i') }}</td><td>{{ \App\Models\MouvementCaisse::TYPES[$m->type] ?? $m->type }}</td>
            <td>{{ $m->billet?->reference }}</td><td>{{ $modes[$m->mode] ?? $m->mode }}</td>
            <td class="r">{{ $f($m->montant) }}</td><td>{{ $m->motif }}</td>
        </tr>
    @endforeach
</table>
<div class="signatures"><div>L'agent</div><div>Le responsable</div></div>
</body>
</html>