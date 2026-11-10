<div>
    @include('filament.partials.finance-styles')

    <style>
        .cp-hero{background:radial-gradient(800px 360px at 95% -30%,rgba(253,224,71,.55),transparent 60%),linear-gradient(135deg,#451a03 0%,#b45309 58%,#d97706 100%)!important}
        .cp-flux{position:relative;z-index:1;display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:20px}
        @media (max-width:760px){.cp-flux{grid-template-columns:repeat(2,1fr)}}
        .cp-etape{position:relative;padding:16px 14px;border-radius:20px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.25);-webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px)}
        .cp-etape:not(:last-child)::after{content:"›";position:absolute;right:-14px;top:50%;transform:translateY(-50%);font-size:1.6rem;font-weight:900;opacity:.7}
        .cp-etape .n{font-size:2.1rem;font-weight:900;line-height:1;letter-spacing:-.03em}
        .cp-etape .l{margin-top:6px;font-size:.7rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;opacity:.9}
        .cp-etape.pulse{background:rgba(255,255,255,.26);box-shadow:0 0 0 0 rgba(255,255,255,.55);animation:cp-pulse 2.2s infinite}
        @keyframes cp-pulse{70%{box-shadow:0 0 0 14px rgba(255,255,255,0)}100%{box-shadow:0 0 0 0 rgba(255,255,255,0)}}
        @media (prefers-reduced-motion:reduce){.cp-etape.pulse{animation:none}}
    </style>

    @php
        $d = $this->donnees();
        $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
    @endphp

    <div class="rf-hero cp-hero" style="padding:24px 28px">
        <div style="position:relative;z-index:1">
            <div class="rf-eyebrow">Colis et courriers</div>
            <div class="rf-big" style="font-size:clamp(1.5rem,3.4vw,2.3rem);margin-bottom:10px">
                {{ $d['aujourdhui'] }} envoi(s) enregistré(s) aujourd'hui
            </div>
            <div class="rf-chips">
                <span class="rf-chip">{{ $fmt($d['encaisse']) }} FCFA encaissés aujourd'hui</span>
                @if ($d['incident'] > 0)
                    <span class="rf-chip" style="background:rgba(239,68,68,.45)">{{ $d['incident'] }} incident(s) à traiter</span>
                @endif
            </div>
        </div>

        <div class="cp-flux">
            <div class="cp-etape"><div class="n">{{ $d['enregistre'] }}</div><div class="l">À expédier</div></div>
            <div class="cp-etape"><div class="n">{{ $d['en_transit'] }}</div><div class="l">En transit</div></div>
            <div class="cp-etape {{ $d['arrive'] > 0 ? 'pulse' : '' }}"><div class="n">{{ $d['arrive'] }}</div><div class="l">À retirer en gare</div></div>
            <div class="cp-etape"><div class="n">{{ $d['livre'] }}</div><div class="l">Livrés (30 j)</div></div>
        </div>
    </div>
</div>