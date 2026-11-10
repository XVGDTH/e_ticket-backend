@php
    $s = $getState()['stats'];
    $raisons = $getState()['raisons'];
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $base = max(1, $s['cibles']);
    $etapes = [
        ['Visés', $s['cibles'], '#6366f1'],
        ['Envoyés', $s['envoyes'], '#0ea5e9'],
        ['Remis', $s['remis'], '#10b981'],
        ['Lus', $s['lus'], '#f59e0b'],
    ];
@endphp

@once
<style>
.en-ligne{display:grid;grid-template-columns:90px 1fr 120px;gap:12px;align-items:center;margin:10px 0}
.en-lib{font-weight:800;font-size:.85rem}
.en-piste{height:26px;border-radius:99px;background:rgba(148,163,184,.2);overflow:hidden}
.en-barre{height:100%;border-radius:99px;background:linear-gradient(90deg,var(--c),color-mix(in srgb,var(--c) 60%,#fff));transition:width .8s}
.en-val{font-weight:900;text-align:right;font-size:.9rem}
.en-val small{opacity:.6;font-weight:700}
.en-aide{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}
.en-puce{padding:4px 12px;border-radius:99px;font-size:.74rem;font-weight:800;background:rgba(148,163,184,.18)}
.en-echec{margin-top:16px;font-size:.82rem}
.en-echec div{padding:6px 0;border-bottom:1px dashed rgba(148,163,184,.4)}
</style>
@endonce

<div>
    @foreach ($etapes as [$libelle, $valeur, $couleur])
        <div class="en-ligne" style="--c: {{ $couleur }}">
            <div class="en-lib">{{ $libelle }}</div>
            <div class="en-piste"><div class="en-barre" style="width: {{ round($valeur / $base * 100) }}%"></div></div>
            <div class="en-val">{{ $fmt($valeur) }} <small>({{ round($valeur / $base * 100) }} %)</small></div>
        </div>
    @endforeach

    <div class="en-aide">
        <span class="en-puce">En attente : {{ $fmt($s['attente']) }}</span>
        <span class="en-puce" style="color:#ef4444">Échecs : {{ $fmt($s['echecs']) }}</span>
        <span class="en-puce">Ignorés (désabonnés, numéros invalides) : {{ $fmt($s['ignores']) }}</span>
    </div>

    @if (count($raisons))
        <div class="en-echec">
            <strong>Pourquoi des messages ont échoué</strong>
            @foreach ($raisons as $r)
                <div>{{ $fmt($r['n']) }} × <code>{{ $r['code'] ?: '—' }}</code> {{ $r['message'] }}</div>
            @endforeach
        </div>
    @endif
</div>