@php
    $etapes = $getState() ?? [];
    $couleurs = ['enregistre' => '#0ea5e9', 'en_transit' => '#f59e0b', 'arrive' => '#0d9488', 'livre' => '#10b981', 'incident' => '#ef4444'];
@endphp

@once
<style>
.cs-frise{position:relative;margin:6px 0 6px 10px;padding-left:28px}
.cs-frise::before{content:"";position:absolute;left:9px;top:6px;bottom:6px;width:3px;border-radius:3px;background:linear-gradient(180deg,#0ea5e9,#f59e0b,#10b981);opacity:.35}
.cs-etape{position:relative;padding:0 0 22px}
.cs-etape:last-child{padding-bottom:0}
.cs-point{position:absolute;left:-28px;top:2px;width:21px;height:21px;border-radius:50%;border:4px solid rgba(255,255,255,.9);box-shadow:0 0 0 3px var(--c),0 8px 14px -6px var(--c);background:var(--c)}
.cs-titre{font-weight:800;font-size:.95rem}
.cs-date{font-size:.72rem;opacity:.65;font-weight:700;margin-left:8px}
.cs-detail{font-size:.82rem;opacity:.8;margin-top:3px}
</style>
@endonce

<div class="cs-frise">
    @forelse ($etapes as $e)
        <div class="cs-etape" style="--c: {{ $couleurs[$e['statut']] ?? '#64748b' }}">
            <span class="cs-point"></span>
            <div><span class="cs-titre">{{ $e['titre'] }}</span><span class="cs-date">{{ $e['date'] }}</span></div>
            @if ($e['detail'])
                <div class="cs-detail">{{ $e['detail'] }}</div>
            @endif
        </div>
    @empty
        <div class="cs-detail">Aucune étape pour le moment.</div>
    @endforelse
</div>