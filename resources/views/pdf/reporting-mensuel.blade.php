<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .sous { color: #555; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th { background: #1f2d4d; color: #fff; text-align: left; padding: 4px 6px; font-size: 9px; }
        td { padding: 4px 6px; border-bottom: 1px solid #ddd; }
        td.n, th.n { text-align: right; white-space: nowrap; }
        tr.total td { font-weight: bold; border-top: 2px solid #1f2d4d; }
        .pos { color: #15803d; } .neg { color: #b91c1c; }
        .cadre { border: 1px solid #ddd; padding: 6px 8px; margin-bottom: 10px; }
    </style>
</head>
<body>
    @php
        $f = fn ($n) => $n === null ? '' : number_format((int) $n, 0, ',', ' ');
        $t = $r['reel']['total'];
    @endphp

    <h1>{{ $titre }}</h1>
    <div class="sous">
        {{ $compagnie }} · du {{ \Carbon\Carbon::parse($r['debut'])->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($r['fin'])->format('d/m/Y') }}
        @if ($cloture) · clôturé le {{ $cloture->cloture_le->format('d/m/Y') }} @else · chiffres provisoires (mois non clôturé) @endif
        @if ($r['budget']) · budget : {{ $r['budget']['libelle'] }} (version {{ $r['budget']['version'] }}) @endif
    </div>

    <div class="cadre">
        Recettes <strong>{{ $f($t['recettes']) }}</strong> FCFA ·
        Coûts <strong>{{ $f($t['couts_total']) }}</strong> FCFA ·
        Résultat <strong class="{{ $t['resultat'] >= 0 ? 'pos' : 'neg' }}">{{ $f($t['resultat']) }}</strong> FCFA ·
        Marge <strong>{{ $t['marge_pct'] !== null ? number_format($t['marge_pct'], 1, ',', ' ').' %' : '—' }}</strong> ·
        Remplissage pondéré <strong>{{ $t['remplissage'] !== null ? number_format($t['remplissage'], 1, ',', ' ').' %' : '—' }}</strong>
    </div>

    <table>
        <tr><th>Palier de rentabilité</th><th class="n">Montant (FCFA)</th></tr>
        <tr><td>Recettes</td><td class="n">{{ $f($t['recettes']) }}</td></tr>
        <tr><td>Marge sur coûts variables (énergie, frais de route, commissions)</td><td class="n">{{ $f($t['marge_variable']) }}</td></tr>
        <tr><td>Marge sur coûts directs (équipage, maintenance, pneus, coûts fixes du car, incidents)</td><td class="n">{{ $f($t['marge_directe']) }}</td></tr>
        <tr><td>Contribution après coûts de gare</td><td class="n">{{ $f($t['contribution']) }}</td></tr>
        <tr class="total"><td>Résultat après frais de siège</td><td class="n">{{ $f($t['resultat']) }}</td></tr>
    </table>

    <table>
        <tr>
            <th>Ligne</th><th class="n">Départs</th><th class="n">Voyageurs</th><th class="n">Recettes</th><th class="n">Coûts</th><th class="n">Résultat</th>
            @if ($r['ecarts']) <th class="n">Budget</th><th class="n">Écart</th><th>Commentaire</th> @endif
        </tr>
        @foreach ($lignes as $l)
            <tr @class(['total' => $loop->last])>
                <td>{{ $l[0] }}</td>
                <td class="n">{{ $f($l[1]) }}</td><td class="n">{{ $f($l[2]) }}</td><td class="n">{{ $f($l[3]) }}</td><td class="n">{{ $f($l[4]) }}</td>
                <td class="n {{ $l[5] >= 0 ? 'pos' : 'neg' }}">{{ $f($l[5]) }}</td>
                @if ($r['ecarts'])
                    <td class="n">{{ $f($l[6]) }}</td>
                    <td class="n {{ $l[7] >= 0 ? 'pos' : 'neg' }}">{{ $f($l[7]) }}</td>
                    <td>{{ $l[8] }}</td>
                @endif
            </tr>
        @endforeach
    </table>
</body>
</html>