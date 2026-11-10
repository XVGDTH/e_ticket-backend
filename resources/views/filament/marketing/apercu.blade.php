@php
    $texte = e((string) ($corps ?? ''));
    $texte = preg_replace('/\*(.+?)\*/s', '<strong>$1</strong>', $texte);
    $texte = nl2br($texte, false);
@endphp

@once
<style>
.wa-tel{max-width:340px;margin:0 auto;border-radius:30px;overflow:hidden;border:8px solid #0f172a;box-shadow:0 30px 50px -28px rgba(0,0,0,.6);background:#0b141a}
.wa-barre{display:flex;align-items:center;gap:10px;padding:12px 14px;background:#075e54;color:#fff}
.wa-avatar{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:#25d366;font-weight:900}
.wa-barre strong{display:block;font-size:.9rem;line-height:1.1}
.wa-barre small{font-size:.68rem;opacity:.8}
.wa-fond{min-height:220px;padding:16px 12px;background:#efeae2 radial-gradient(rgba(0,0,0,.05) 1px,transparent 1px) 0 0/14px 14px}
.wa-bulle{position:relative;max-width:92%;padding:9px 12px 18px;border-radius:0 12px 12px 12px;background:#fff;color:#111b21;font-size:.86rem;line-height:1.4;box-shadow:0 1px 1px rgba(0,0,0,.15);word-break:break-word}
.wa-pied{margin-top:6px;font-size:.72rem;color:#667781}
.wa-heure{position:absolute;right:9px;bottom:4px;font-size:.62rem;color:#667781}
.wa-bouton{max-width:92%;margin-top:4px;padding:9px;border-radius:12px;background:#fff;color:#027eb5;text-align:center;font-size:.84rem;font-weight:700;box-shadow:0 1px 1px rgba(0,0,0,.15)}
</style>
@endonce

<div class="wa-tel">
    <div class="wa-barre">
        <span class="wa-avatar">{{ mb_strtoupper(mb_substr((string) config('app.name', 'C'), 0, 1)) }}</span>
        <div><strong>{{ config('app.name') }}</strong><small>compte professionnel</small></div>
    </div>
    <div class="wa-fond">
        <div class="wa-bulle">
            @if (filled($corps ?? null))
                {!! $texte !!}
            @else
                <em style="opacity:.6">Votre message apparaîtra ici…</em>
            @endif
            @if (filled($pied ?? null))
                <div class="wa-pied">{{ $pied }}</div>
            @endif
            <span class="wa-heure">{{ now()->format('H:i') }}</span>
        </div>
        @if (! empty($stop))
            <div class="wa-bouton">↩ Ne plus recevoir</div>
        @endif
    </div>
</div>