@php
    $f = fn ($v) => number_format((int) $v, 0, ',', ' ');
    $heure = str_replace(':', 'h', \App\Services\Billetterie::normaliserHeure((string) $b->heure));
    $thermique = $format === 'thermique';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Billet {{ $b->reference }}</title>
<style>
    @if($thermique) @page { size: 80mm auto; margin: 3mm; } @else @page { size: A5; margin: 10mm; } @endif
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; color: #000; margin: 0 auto; {{ $thermique ? 'width: 72mm; font-size: 12px;' : 'max-width: 128mm; font-size: 14px;' }} }
    .c { text-align: center; } .g { font-weight: bold; } .t { font-size: {{ $thermique ? '15px' : '20px' }}; }
    hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
    table { width: 100%; border-collapse: collapse; } td { padding: 1px 0; vertical-align: top; } td.r { text-align: right; }
    .dup { border: 2px solid #000; padding: 2px; margin: 4px 0; font-weight: bold; letter-spacing: 2px; }
    .qr svg { width: {{ $thermique ? '38mm' : '45mm' }}; height: auto; }
    .petit { font-size: {{ $thermique ? '10px' : '11px' }}; }
    .barre { text-align: right; margin: 6px 0; } button { padding: 6px 12px; }
    @media print { .barre { display: none; } }
</style>
</head>
<body onload="window.print()">
<div class="barre">
    <button onclick="window.print()">Imprimer</button>
    <a href="?format={{ $thermique ? 'a5' : 'thermique' }}"><button>{{ $thermique ? 'Format A5' : 'Format ticket 80 mm' }}</button></a>
</div>

<div class="c g t">{{ $b->route?->compagnie?->nom }}</div>
<div class="c">BILLET DE TRANSPORT</div>
@if($duplicata)<div class="c dup">DUPLICATA</div>@endif
<hr>
<div class="c g t">{{ $b->gare?->quartier }} → {{ $b->route?->villeArrivee?->nom }}</div>
<div class="c g" style="font-size: {{ $thermique ? '16px' : '22px' }}">{{ $b->date_voyage->format('d/m/Y') }} à {{ $heure }}</div>
<hr>
<table>
    <tr><td colspan="2" class="g">{{ $b->travelers_count }} voyageur(s)</td></tr>
    @foreach($b->voyageurs as $i => $v)
        <tr><td>{{ $i + 1 }}. {{ $v['nom'] }}</td><td class="r g">{{ $v['code'] }}</td></tr>
    @endforeach
</table>
<hr>
<table>
    @if($b->remise > 0)<tr><td>Remise</td><td class="r">− {{ $f($b->remise) }} FCFA</td></tr>@endif
    <tr class="g"><td>TOTAL PAYÉ</td><td class="r">{{ $f($b->montant_transport) }} FCFA</td></tr>
    <tr><td>Paiement</td><td class="r">{{ $b->operateur_paiement }}</td></tr>
    @if($b->reference_paiement)<tr><td>Transaction</td><td class="r">{{ $b->reference_paiement }}</td></tr>@endif
</table>
<hr>
<div class="c qr">@if($qr){!! $qr !!}@endif</div>
<div class="c g">{{ $b->reference }}</div>
<div class="c petit">Valable jusqu'au {{ $b->valide_jusqu_au?->format('d/m/Y') }}</div>
<hr>
<div class="petit c">{{ config('guichet.conditions') }}</div>
<div class="petit c">Vendu le {{ $b->created_at->format('d/m/Y H:i') }}@if($agent) — {{ trim($agent->prenom.' '.$agent->nom) }}@endif</div>
</body>
</html>