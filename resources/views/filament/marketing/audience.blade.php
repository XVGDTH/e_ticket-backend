@php
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $sansConsentement = max(0, $a['dans_segments'] - $a['consentants'] - 0);
@endphp

@once
<style>
.au-boite{display:flex;flex-wrap:wrap;gap:18px;align-items:center;padding:18px 20px;border-radius:22px;color:#fff;background:linear-gradient(135deg,#047857,#10b981);box-shadow:0 22px 36px -24px #047857}
.au-n{font-size:2.6rem;font-weight:900;letter-spacing:-.04em;line-height:1}
.au-l{font-size:.72rem;letter-spacing:.14em;text-transform:uppercase;opacity:.9;font-weight:800}
.au-notes{flex:1;min-width:230px;font-size:.8rem;line-height:1.55}
.au-notes b{font-weight:900}
</style>
@endonce

<div class="au-boite">
    <div>
        <div class="au-n">{{ $fmt($a['cibles']) }}</div>
        <div class="au-l">client(s) seront contactés</div>
    </div>
    <div class="au-notes">
        <div><b>{{ $fmt($a['dans_segments']) }}</b> client(s) correspondent à ces types.</div>
        <div><b>{{ $fmt($a['consentants']) }}</b> ont accepté de recevoir les offres par WhatsApp.</div>
        @if ($a['en_pause'] > 0)
            <div><b>{{ $fmt($a['en_pause']) }}</b> sont en pause : ils ont reçu une campagne depuis moins de {{ $pause }} jours.</div>
        @endif
        @if ($cout > 0 && $a['cibles'] > 0)
            <div>Coût estimé : <b>{{ $fmt($a['cibles'] * $cout) }} FCFA</b></div>
        @endif
    </div>
</div>