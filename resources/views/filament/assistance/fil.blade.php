@php $messages = $getState() ?? []; @endphp

@once
<style>
.as-fil{display:flex;flex-direction:column;gap:14px;max-height:520px;overflow:auto;padding:6px 4px}
.as-ligne{display:flex}
.as-ligne.staff{justify-content:flex-end}
.as-bulle{max-width:min(78%,560px);padding:12px 16px;border-radius:20px;box-shadow:0 10px 24px -18px rgba(15,23,42,.45)}
.as-ligne.client .as-bulle{background:rgba(148,163,184,.16);border-bottom-left-radius:6px}
.as-ligne.staff .as-bulle{color:#fff;background:linear-gradient(135deg,#0f766e,#14b8a6);border-bottom-right-radius:6px}
.as-meta{font-size:.7rem;font-weight:700;opacity:.75;margin-bottom:4px}
.as-texte{font-size:.9rem;line-height:1.45;word-break:break-word}
.as-vide{text-align:center;opacity:.6;padding:24px}
</style>
@endonce

<div class="as-fil">
    @forelse ($messages as $m)
        <div class="as-ligne {{ $m['type'] === 'staff' ? 'staff' : 'client' }}">
            <div class="as-bulle">
                <div class="as-meta">{{ $m['auteur'] }} · {{ $m['date'] }}</div>
                <div class="as-texte">{!! nl2br(e($m['corps'])) !!}</div>
            </div>
        </div>
    @empty
        <div class="as-vide">Aucun message.</div>
    @endforelse
</div>