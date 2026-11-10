@php $n = 0; $total = $billets->sum('travelers_count'); @endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Manifeste {{ $date->format('d/m/Y') }} {{ $heure }}</title>
<style>
    @page { size: A4; margin: 12mm; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #000; }
    h1 { font-size: 18px; margin: 0 0 4px; } table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 1px solid #888; padding: 4px 6px; text-align: left; } th { background: #eee; }
    .barre { text-align: right; } .signatures { display: flex; justify-content: space-between; margin-top: 40px; }
    .signatures div { width: 30%; border-top: 1px solid #000; text-align: center; padding-top: 4px; }
    @media print { .barre { display: none; } }
</style>
</head>
<body>
<div class="barre"><button onclick="window.print()">Imprimer</button></div>
<h1>MANIFESTE DE DÉPART — {{ $route->compagnie?->nom }}</h1>
<div><strong>{{ $route->gareDepart?->quartier }} → {{ $route->villeArrivee?->nom }}</strong>
    — {{ $date->format('d/m/Y') }} à {{ str_replace(':', 'h', $heure) }} — {{ $total }} voyageur(s)</div>
<table>
    <tr><th>N°</th><th>Voyageur</th><th>Code</th><th>Billet</th><th>Canal</th><th>État</th><th>Téléphone</th><th>Émargement</th></tr>
    @foreach($billets as $b)
        @foreach($b->voyageurs as $v)
            @php $n++; @endphp
            <tr>
                <td>{{ $n }}</td><td>{{ $v['nom'] }}</td><td><strong>{{ $v['code'] }}</strong></td><td>{{ $b->reference }}</td>
                <td>{{ $b->canal === 'guichet' ? 'Guichet' : 'Application' }}</td><td>{{ $b->statut->getLabel() }}</td>
                <td>{{ $b->telephone_client }}</td><td></td>
            </tr>
        @endforeach
    @endforeach
</table>
<div class="signatures"><div>Agent de gare</div><div>Chauffeur</div><div>Contrôleur</div></div>
</body>
</html>