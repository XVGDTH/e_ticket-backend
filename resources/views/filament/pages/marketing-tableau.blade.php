<x-filament-panels::page>
    @include('filament.partials.finance-styles')

    <style>
        .mk-hero{background:radial-gradient(800px 380px at 92% -25%,rgba(37,211,102,.55),transparent 60%),linear-gradient(135deg,#052e16 0%,#047857 58%,#10b981 100%)!important}
        .mk-seg{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
        @media (max-width:760px){.mk-seg{grid-template-columns:repeat(2,1fr)}}
        .mk-case{position:relative;overflow:hidden;border-radius:20px;padding:16px;background:var(--card);border:1px solid var(--line)}
        .mk-case::before{content:"";position:absolute;inset:0 0 auto 0;height:5px;background:var(--c)}
        .mk-case .n{font-size:1.9rem;font-weight:900;letter-spacing:-.03em;color:var(--c)}
        .mk-case .l{font-size:.7rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--muted)}
        .mk-pastille{display:inline-block;padding:3px 12px;border-radius:99px;font-size:.72rem;font-weight:900}
        .mk-code{font-family:monospace;font-size:.8rem;padding:2px 8px;border-radius:8px;background:var(--soft)}
    </style>

    @php
        $d = $this->donnees();
        $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
        $taux = $d['clients'] > 0 ? round($d['consentants'] / $d['clients'] * 100) : 0;
        $max = max(1, collect($d['top'])->max('n'));
    @endphp

    <div class="rf">
        <div class="rf-hero mk-hero">
            <div class="rf-hero-grid">
                <div>
                    <div class="rf-eyebrow">Clients joignables par WhatsApp</div>
                    <div class="rf-big"
                         wire:key="mk-{{ $d['joignables'] }}"
                         x-data="{ v: 0, cible: {{ (int) $d['joignables'] }} }"
                         x-init="const s = performance.now(); const tick = (now) => { const k = Math.min(1, (now - s) / 900); v = Math.round(cible * (1 - Math.pow(1 - k, 3))); if (k < 1) requestAnimationFrame(tick); }; requestAnimationFrame(tick)">
                        <span x-text="v.toLocaleString('fr-FR')">{{ $fmt($d['joignables']) }}</span><small>fidèles et réguliers</small>
                    </div>
                    <div class="rf-chips">
                        <span class="rf-chip">{{ $fmt($d['consentants']) }} client(s) ont accepté les offres ({{ $taux }} %)</span>
                        <!-- <span class="rf-chip">WhatsApp : {{ $d['configure'] ? 'identifiants renseignés' : 'non configuré' }}</span> -->
                        <span class="rf-chip">WhatsApp : {{ $d['configure'] ? 'identifiants renseignés' : 'non configuré' }}</span>
                    </div>
                </div>
                <div style="display:grid;place-items:center">
                    <svg viewBox="0 0 42 42" width="150" height="150" style="transform:rotate(-90deg)">
                        <circle cx="21" cy="21" r="15.9155" fill="none" stroke="rgba(255,255,255,.22)" stroke-width="4"/>
                        <circle cx="21" cy="21" r="15.9155" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-dasharray="{{ $taux }} {{ 100 - $taux }}"/>
                    </svg>
                    <div style="margin-top:-96px;text-align:center;font-weight:900;font-size:1.7rem">{{ $taux }} %<div style="font-size:.6rem;letter-spacing:.14em;text-transform:uppercase;opacity:.85">ont accepté</div></div>
                </div>
            </div>
        </div>

        <div class="mk-seg">
            @foreach ([['fidele', 'Fidèles', '#10b981'], ['regulier', 'Réguliers', '#0ea5e9'], ['occasionnel', 'Occasionnels', '#f59e0b'], ['inactif', 'Inactifs', '#94a3b8']] as [$cle, $lib, $couleur])
                <div class="mk-case" style="--c: {{ $couleur }}">
                    <div class="n">{{ $fmt($d['segments'][$cle]) }}</div>
                    <div class="l">{{ $lib }}</div>
                </div>
            @endforeach
        </div>

        <div class="rf-two">
            <div class="rf-card">
                <h3>Où sont vos clients fidèles ?</h3>
                <div class="sub">Fidèles et réguliers ayant accepté les offres, par compagnie.</div>
                @forelse ($d['top'] as $i => $t)
                    <div class="rf-row">
                        <div class="rf-rank">{{ $i + 1 }}</div>
                        <div>{{ $t['nom'] }}<div class="rf-track"><i style="width: {{ round($t['n'] / $max * 100) }}%"></i></div></div>
                        <div class="rf-val">{{ $fmt($t['n']) }}</div>
                    </div>
                @empty
                    <div class="sub">Aucun client fidèle n'a encore accepté les offres. Lancez « Recalculer les préférences » une fois que des clients ont accepté dans l'application.</div>
                @endforelse
            </div>

            <div class="rf-card">
                <h3>Connexion WhatsApp</h3>
                <div class="sub">Compte configuré : <strong>{{ $d['configure'] ? 'oui' : 'non' }}</strong></div>

                <button type="button" class="rf-pill" wire:click="verifierConnexion" wire:loading.attr="disabled">Vérifier la connexion</button>

                @if ($etat)
                    <div style="margin-top:12px;font-size:.85rem">
                        @if ($etat['ok'])
                            <div>✅ <strong>{{ $etat['nom'] }}</strong> · {{ $etat['numero'] }}</div>
                            <div>Qualité : <strong>{{ $etat['qualite'] ?? '—' }}</strong></div>
                            <div>Plafond d'envoi : <strong>{{ $etat['plafond'] ?? 'non communiqué' }}</strong></div>
                        @else
                            <div style="color:#ef4444">⛔ {{ $etat['erreur'] }}</div>
                        @endif
                    </div>
                @endif

                <div style="margin-top:14px;font-size:.78rem;opacity:.8">
                    Adresse du webhook :<br>
                    <span class="mk-code">{{ $d['webhook'] }}</span>
                </div>
            </div>
        </div>

        <div class="rf-card">
            <h3>Dernières campagnes</h3>
            <table class="rf-table">
                <thead><tr><th>Campagne</th><th>Cible</th><th>Statut</th><th class="n">Clients visés</th></tr></thead>
                <tbody>
                    @forelse ($d['campagnes'] as $c)
                        <tr>
                            <td><strong>{{ $c->nom }}</strong></td>
                            <td>{{ $c->compagnie?->nom }}{{ $c->gare ? ' · ' . $c->gare->quartier : '' }}</td>
                            <td>{{ \App\Models\MarketingCampagne::STATUTS[$c->statut] ?? $c->statut }}</td>
                            <td class="n">{{ $fmt($c->nb_cibles) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">Aucune campagne pour l'instant.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>