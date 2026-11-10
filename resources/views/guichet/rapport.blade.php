@php
    $f = fn ($v) => number_format((int) $v, 0, ',', ' ') . ' FCFA';
    $t = $r['totaux'];
    $cartes = [
        ["Chiffre d'affaires", $f($t['chiffre_affaires'])],
        ['Billets vendus', $t['billets']],
        ['Voyageurs', $t['voyageurs']],
        ['Remises accordées', $f($t['remises'])],
        ['Annulés / remboursés', $t['annules'] . ' (' . $f($t['montant_annule']) . ')'],
        ['Net encaissé en caisse', $f($t['net_encaisse'])],
    ];
    $tables = [
        'Par mode de paiement' => [$r['par_mode'], ['mode' => 'Mode', 'montant' => 'Net encaissé']],
        'Par agent' => [$r['par_agent'], ['agent' => 'Agent', 'billets' => 'Billets', 'voyageurs' => 'Voyageurs', 'montant' => 'Montant']],
        'Par destination' => [$r['par_destination'], ['trajet' => 'Trajet', 'billets' => 'Billets', 'voyageurs' => 'Voyageurs', 'montant' => 'Montant']],
        'Par jour' => [$r['par_jour'], ['jour' => 'Jour', 'billets' => 'Billets', 'montant' => 'Montant']],
    ];
@endphp
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:12px 0">
    @foreach($cartes as $carte)
        <div style="border:1px solid rgba(128,128,128,.35);border-radius:8px;padding:10px">
            <div style="font-size:12px;opacity:.7">{{ $carte[0] }}</div>
            <div style="font-size:18px;font-weight:700">{{ $carte[1] }}</div>
        </div>
    @endforeach
</div>

@foreach($tables as $titre => $def)
    @php $lignes = $def[0]; $colonnes = $def[1]; @endphp
    <h3 style="margin:16px 0 6px;font-weight:700">{{ $titre }}</h3>
    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <tr>
                @foreach($colonnes as $cle => $nom)
                    <th style="text-align:{{ $cle === 'montant' ? 'right' : 'left' }};padding:6px;border-bottom:1px solid rgba(128,128,128,.4)">{{ $nom }}</th>
                @endforeach
            </tr>
            @forelse($lignes as $l)
                <tr>
                    @foreach($colonnes as $cle => $nom)
                        <td style="text-align:{{ $cle === 'montant' ? 'right' : 'left' }};padding:6px;border-bottom:1px solid rgba(128,128,128,.2)">{{ $cle === 'montant' ? $f($l[$cle]) : $l[$cle] }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($colonnes) }}" style="padding:8px;opacity:.6">Aucune vente sur cette période.</td></tr>
            @endforelse
        </table>
    </div>
@endforeach