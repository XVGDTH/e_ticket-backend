<x-filament-panels::page>
    @include('filament.partials.finance-styles')
    @include('filament.partials.modules-styles')

    <style>
        .gf-echelle{display:grid;grid-auto-flow:column;grid-auto-columns:minmax(150px,1fr);gap:14px;overflow-x:auto;padding-bottom:6px}
        .gf-col{--c:#0d9488;display:flex;flex-direction:column;align-items:stretch;min-width:150px}
        .gf-col.intl{--c:#6366f1}
        .gf-zone{height:260px;display:flex;flex-direction:column;justify-content:flex-end;align-items:center}
        .gf-valeur{font-size:1.9rem;font-weight:900;letter-spacing:-.03em;color:var(--c);line-height:1}
        .gf-valeur small{font-size:.5em;margin-left:4px;opacity:.7}
        .gf-barre{width:100%;margin-top:8px;border-radius:18px 18px 8px 8px;background:linear-gradient(180deg,var(--c),color-mix(in srgb,var(--c) 55%,#000));transform-origin:bottom;animation:gf-grow .8s cubic-bezier(.2,.8,.2,1) both;box-shadow:0 20px 30px -20px var(--c)}
        @keyframes gf-grow{from{transform:scaleY(0)}to{transform:scaleY(1)}}
        .gf-tranche{margin-top:10px;text-align:center;font-weight:800;font-size:.84rem}
        .gf-tranche small{display:block;font-weight:600;opacity:.6;font-size:.68rem;letter-spacing:.06em;text-transform:uppercase}
        .gf-badge{align-self:center;margin-top:8px;padding:3px 11px;border-radius:99px;font-size:.66rem;font-weight:800;background:var(--soft);color:var(--muted)}
        .gf-badge.propre{background:color-mix(in srgb,var(--c) 18%,transparent);color:var(--c)}
        .gf-badge.compagnie{background:rgba(245,158,11,.16);color:#b45309}
        .gf-saisie{display:flex;align-items:center;gap:6px;margin-top:10px;padding:6px 10px;border-radius:14px;border:1px solid var(--line);background:var(--card)}
        .gf-saisie:focus-within{border-color:var(--c);box-shadow:0 0 0 3px color-mix(in srgb,var(--c) 22%,transparent)}
        .gf-saisie input{width:100%;min-width:0;border:0;outline:0;background:transparent;color:inherit;font-weight:800;font-size:1rem;text-align:right}
        .gf-saisie span{font-size:.68rem;font-weight:700;opacity:.55}
        .gf-actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
        .gf-btn{display:inline-flex;align-items:center;gap:8px;padding:12px 22px;border-radius:14px;border:0;cursor:pointer;color:#fff;font-weight:800;background:linear-gradient(135deg,#0f766e,#14b8a6);box-shadow:0 16px 28px -16px #0f766e}
        .gf-btn:disabled{opacity:.6}
    </style>

    @php
        $d = $this->donnees();
        $max = max(1, collect($d['paliers'])->max('effectif'));
        $niveauxLibelle = ['base' => 'Grille de base', 'compagnie' => 'Compagnie', 'gare' => 'Gare'];
        $sourceLibelle = [
            'base' => $d['niveau'] === 'base' ? 'Valeur de base' : 'Grille de base',
            'compagnie' => 'Hérité de la compagnie',
            'propre' => 'Personnalisé',
        ];
    @endphp

    <div class="rf">
        <div class="rf-bar">
            @if ($d['superAdmin'])
                <select wire:model.live="compagnieId" class="rf-select">
                    <option value="">Grille de base (toutes les compagnies)</option>
                    @foreach ($d['compagnies'] as $id => $nom)
                        <option value="{{ $id }}">{{ $nom }}</option>
                    @endforeach
                </select>
            @endif

            @if (count($d['gares']) > 0)
                <select wire:model.live="gareId" class="rf-select">
                    @if ($d['peutToutelaCompagnie'])
                        <option value="">Toutes les gares de la compagnie</option>
                    @endif
                    @foreach ($d['gares'] as $id => $nom)
                        <option value="{{ $id }}">{{ $nom }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        <div class="rf-hero">
            <div class="rf-hero-grid" style="grid-template-columns:1fr">
                <div>
                    <div class="rf-eyebrow">Frais de service · {{ $niveauxLibelle[$d['niveau']] }}</div>
                    <div class="rf-big" style="font-size:clamp(1.6rem,4vw,2.6rem)">{{ $d['titre'] }}</div>
                    <div class="rf-chips">
                        <span class="rf-chip">Payés une seule fois par achat, quel que soit le nombre de billets</span>
                        <span class="rf-chip">Tranche lue sur le prix d'un billet par voyageur</span>
                        <span class="rf-chip">Les billets déjà vendus gardent leurs frais</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="rf-card">
            <h3>L'escalier des frais</h3>
            <div class="sub">
                @if ($d['niveau'] === 'base')
                    Ces montants s'appliquent à toutes les compagnies qui n'ont pas les leurs.
                @else
                    Laissez une case vide pour suivre la grille du dessus ; remplissez-la pour fixer vos propres frais sur cette tranche.
                @endif
            </div>

            <div class="gf-echelle">
                @foreach ($d['paliers'] as $p)
                    <div class="gf-col {{ $p['trajet'] === 'international' ? 'intl' : '' }}" wire:key="palier-{{ $p['cle'] }}-{{ $d['niveau'] }}">
                        <div class="gf-zone">
                            <div class="gf-valeur">{{ number_format($p['effectif'], 0, ',', ' ') }}<small>FCFA</small></div>
                            <div class="gf-barre" style="height: {{ 36 + (int) round($p['effectif'] / $max * 150) }}px"></div>
                        </div>
                        <div class="gf-tranche">
                            <small>{{ $p['trajet'] === 'international' ? 'International' : 'Billet de' }}</small>
                            {{ $p['libelle'] }}@if ($p['trajet'] !== 'international') <span style="opacity:.6;font-weight:600"> FCFA</span>@endif
                        </div>
                        <span class="gf-badge {{ $p['source'] }}">{{ $sourceLibelle[$p['source']] }}</span>
                        <label class="gf-saisie">
                            <input type="text" inputmode="numeric" autocomplete="off"
                                   wire:model="saisies.{{ $p['cle'] }}"
                                   placeholder="{{ $d['niveau'] === 'base' ? $p['effectif'] : $p['heritee'] }}">
                            <span>FCFA</span>
                        </label>
                        @if ($d['niveau'] !== 'base' && $p['source'] === 'propre')
                            <button type="button" class="md-tout" style="color:var(--c)" wire:click="retablir('{{ $p['cle'] }}')">Rétablir</button>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="gf-actions">
            <button type="button" class="gf-btn" wire:click="enregistrer" wire:loading.attr="disabled">
                <x-filament::icon icon="heroicon-o-check-circle" style="width:20px;height:20px" />
                Enregistrer la grille
            </button>

            @if ($d['superAdmin'] && $d['niveau'] === 'base')
                <button type="button" class="rf-pill" wire:click="restaurerBase"
                        wire:confirm="Remettre toutes les tranches de la grille de base à leurs valeurs d'origine ?">
                    Restaurer la grille de base
                </button>
            @endif
        </div>
    </div>
</x-filament-panels::page>