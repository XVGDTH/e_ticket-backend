<x-filament-panels::page>
    @include('filament.partials.finance-styles')

    <div class="ai-dashboard">
        <style>
            .ai-dashboard {
                --ai-ink: #0f172a;
                --ai-muted: #64748b;
                --ai-card: #fff;
                --ai-line: rgba(15, 23, 42, .09);
                --ai-soft: #f1f5f9;
                display: grid;
                gap: 1.25rem;
                color: var(--ai-ink);
            }
            .dark .ai-dashboard {
                --ai-ink: #f1f5f9;
                --ai-muted: #94a3b8;
                --ai-card: rgba(30, 41, 59, .78);
                --ai-line: rgba(255, 255, 255, .1);
                --ai-soft: rgba(255, 255, 255, .07);
            }
            .ai-hero {
                position: relative;
                overflow: hidden;
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto;
                align-items: center;
                gap: 1.5rem;
                padding: clamp(1.4rem, 3vw, 2.2rem);
                border-radius: 1.6rem;
                color: #fff;
                background:
                    radial-gradient(700px 340px at 92% -20%, rgba(45, 212, 191, .45), transparent 60%),
                    radial-gradient(500px 320px at 0% 120%, rgba(245, 158, 11, .26), transparent 60%),
                    linear-gradient(130deg, #0f172a, #134e4a 70%, #0f766e);
                box-shadow: 0 25px 55px -35px rgba(15, 118, 110, .8);
            }
            .ai-hero-copy { position: relative; z-index: 1; }
            .ai-eyebrow { font-size: .7rem; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; opacity: .76; }
            .ai-hero h2 { margin: .45rem 0; font-size: clamp(1.5rem, 3vw, 2.15rem); font-weight: 850; letter-spacing: -.03em; line-height: 1.15; }
            .ai-hero p { max-width: 46rem; margin: 0; font-size: .9rem; line-height: 1.55; opacity: .82; }
            .ai-hero-mark { display: grid; width: 5rem; height: 5rem; place-items: center; border: 1px solid rgba(255,255,255,.25); border-radius: 1.5rem; background: rgba(255,255,255,.12); }
            .ai-hero-mark svg { width: 2.5rem; height: 2.5rem; }
            .ai-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .65rem; }
            .ai-company { min-width: min(100%, 17rem); }
            .ai-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .8rem; }
            .ai-stat { position: relative; overflow: hidden; min-width: 0; padding: 1rem 1.1rem; border: 1px solid var(--ai-line); border-radius: 1.1rem; background: var(--ai-card); }
            .ai-stat::before { position: absolute; inset: 0 auto 0 0; width: 4px; background: var(--ai-accent, #0f766e); content: ""; }
            .ai-stat-label { color: var(--ai-muted); font-size: .68rem; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; }
            .ai-stat-value { margin-top: .25rem; font-size: 1.75rem; font-weight: 850; line-height: 1.1; font-variant-numeric: tabular-nums; }
            .ai-stat-hint { margin-top: .3rem; color: var(--ai-muted); font-size: .75rem; }
            .ai-sections { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; gap: 1rem; }
            .ai-section { min-width: 0; padding: 1.15rem; border: 1px solid var(--ai-line); border-radius: 1.35rem; background: var(--ai-card); }
            .ai-section-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .8rem; margin-bottom: 1rem; }
            .ai-section-title { display: flex; align-items: center; gap: .65rem; }
            .ai-section-icon { display: grid; width: 2.4rem; height: 2.4rem; flex: none; place-items: center; border-radius: .8rem; color: var(--ai-accent); background: color-mix(in srgb, var(--ai-accent) 12%, transparent); }
            .ai-section-icon svg { width: 1.25rem; height: 1.25rem; }
            .ai-section h3 { margin: 0; font-size: 1rem; font-weight: 850; }
            .ai-section-sub { margin-top: .15rem; color: var(--ai-muted); font-size: .76rem; }
            .ai-count { display: inline-flex; min-width: 1.7rem; height: 1.7rem; align-items: center; justify-content: center; padding: 0 .45rem; border-radius: 999px; color: var(--ai-accent); background: color-mix(in srgb, var(--ai-accent) 12%, transparent); font-size: .75rem; font-weight: 800; }
            .ai-card-list { display: grid; gap: .7rem; }
            .ai-card { display: grid; gap: .65rem; padding: .95rem; border: 1px solid var(--ai-line); border-radius: 1rem; border-left: 4px solid var(--ai-accent, #0f766e); background: color-mix(in srgb, var(--ai-card) 94%, var(--ai-soft)); }
            .ai-card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: .65rem; }
            .ai-card-title { margin: 0; font-size: .9rem; font-weight: 800; line-height: 1.35; }
            .ai-badges { display: flex; flex-wrap: wrap; gap: .35rem; }
            .ai-badge { display: inline-flex; align-items: center; gap: .25rem; padding: .18rem .55rem; border: 1px solid var(--ai-line); border-radius: 999px; color: var(--ai-muted); font-size: .66rem; font-weight: 750; white-space: nowrap; }
            .ai-badge.is-danger { border-color: rgba(239, 68, 68, .25); color: #dc2626; background: rgba(239, 68, 68, .08); }
            .ai-badge.is-warning { border-color: rgba(245, 158, 11, .28); color: #b45309; background: rgba(245, 158, 11, .1); }
            .dark .ai-badge.is-warning { color: #fbbf24; }
            .dark .ai-badge.is-danger { color: #f87171; }
            .ai-card-copy { margin: 0; color: var(--ai-muted); font-size: .78rem; line-height: 1.5; overflow-wrap: anywhere; }
            .ai-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .45rem .8rem; color: var(--ai-muted); font-size: .73rem; }
            .ai-meta span { min-width: 0; overflow-wrap: anywhere; }
            .ai-actions { display: flex; flex-wrap: wrap; gap: .45rem; padding-top: .55rem; border-top: 1px dashed var(--ai-line); }
            .ai-action { padding: .3rem .65rem; border: 1px solid var(--ai-line); border-radius: 999px; color: var(--ai-ink); background: transparent; font-size: .72rem; font-weight: 750; cursor: pointer; transition: background .15s, border-color .15s; }
            .ai-action:hover { border-color: var(--ai-accent, #0f766e); background: color-mix(in srgb, var(--ai-accent, #0f766e) 9%, transparent); }
            .ai-empty { display: grid; justify-items: center; gap: .45rem; padding: 1.6rem 1rem; border: 1px dashed var(--ai-line); border-radius: 1rem; color: var(--ai-muted); text-align: center; }
            .ai-empty svg { width: 1.8rem; height: 1.8rem; color: var(--ai-accent); }
            .ai-empty strong { color: var(--ai-ink); font-size: .88rem; }
            .ai-empty span { max-width: 22rem; font-size: .76rem; line-height: 1.45; }
            @media (max-width: 1050px) {
                .ai-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                .ai-sections { grid-template-columns: 1fr; }
            }
            @media (max-width: 560px) {
                .ai-hero { grid-template-columns: 1fr; }
                .ai-hero-mark { display: none; }
                .ai-stats { gap: .55rem; }
                .ai-stat { padding: .85rem .8rem .85rem 1rem; }
                .ai-stat-value { font-size: 1.45rem; }
                .ai-section { padding: .8rem; }
                .ai-meta { grid-template-columns: 1fr; }
            }
            @media (prefers-reduced-motion: reduce) {
                .ai-dashboard *, .ai-dashboard *::before, .ai-dashboard *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; }
            }
        </style>

        @php
            $frequences = [
                'quotidienne' => 'Quotidien',
                'hebdomadaire' => 'Hebdomadaire',
                'mensuelle' => 'Mensuel',
                'trimestrielle' => 'Trimestriel',
                'annuelle' => 'Annuel',
                'ponctuelle' => 'Ponctuel',
            ];
            $risques = ['faible' => 'Risque faible', 'moyen' => 'Risque moyen', 'eleve' => 'Risque élevé', 'critique' => 'Risque critique'];
            $priorites = ['basse' => 'Priorité basse', 'normale' => 'Priorité normale', 'haute' => 'Priorité haute', 'critique' => 'Priorité critique'];
            $statuts = ['ouverte' => 'Ouverte', 'en_cours' => 'En cours', 'terminee' => 'Terminée'];
        @endphp

        <section class="ai-hero">
            <div class="ai-hero-copy">
                <div class="ai-eyebrow">Pilotage · conformité · amélioration continue</div>
                <h2>Un contrôle interne plus clair, action après action.</h2>
                <p>Organisez les vérifications, attribuez un responsable et suivez chaque recommandation jusqu’à sa résolution.</p>
            </div>
            <div class="ai-hero-mark" aria-hidden="true">
                <x-filament::icon icon="heroicon-o-shield-check" />
            </div>
        </section>

        @if ($d['compagnies']->count() > 1)
            <div class="ai-toolbar">
                <div class="ai-company">
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="compagnieId" aria-label="Compagnie auditée">
                            @foreach ($d['compagnies'] as $id => $nom)
                                <option value="{{ $id }}">{{ $nom }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            </div>
        @endif

        @if ($d['nomCompagnie'] === '—')
            <div class="ai-empty">
                <x-filament::icon icon="heroicon-o-building-office" />
                <strong>Aucune compagnie sélectionnée</strong>
                <span>Sélectionnez une compagnie pour afficher son plan de contrôles et ses recommandations.</span>
            </div>
        @else
            <div class="ai-stats">
                <article class="ai-stat" style="--ai-accent:#0f766e">
                    <div class="ai-stat-label">Contrôles actifs</div>
                    <div class="ai-stat-value">{{ $d['statistiques']['controles_actifs'] }}</div>
                    <div class="ai-stat-hint">Au plan de contrôle · {{ $d['nomCompagnie'] }}</div>
                </article>
                <article class="ai-stat" style="--ai-accent:#dc2626">
                    <div class="ai-stat-label">Contrôles en retard</div>
                    <div class="ai-stat-value">{{ $d['statistiques']['controles_en_retard'] }}</div>
                    <div class="ai-stat-hint">Échéance dépassée</div>
                </article>
                <article class="ai-stat" style="--ai-accent:#d97706">
                    <div class="ai-stat-label">Recommandations ouvertes</div>
                    <div class="ai-stat-value">{{ $d['statistiques']['recommandations_ouvertes'] }}</div>
                    <div class="ai-stat-hint">À traiter ou en cours</div>
                </article>
                <article class="ai-stat" style="--ai-accent:#be123c">
                    <div class="ai-stat-label">Recommandations en retard</div>
                    <div class="ai-stat-value">{{ $d['statistiques']['recommandations_en_retard'] }}</div>
                    <div class="ai-stat-hint">Action corrective à relancer</div>
                </article>
            </div>

            <div class="ai-sections">
                <section class="ai-section" style="--ai-accent:#0f766e">
                    <div class="ai-section-head">
                        <div class="ai-section-title">
                            <span class="ai-section-icon"><x-filament::icon icon="heroicon-o-clipboard-document-check" /></span>
                            <div>
                                <h3>Plan de contrôles</h3>
                                <div class="ai-section-sub">Objectifs, fréquence, risque et responsable</div>
                            </div>
                        </div>
                        <span class="ai-count">{{ $d['controles']->count() }}</span>
                    </div>

                    <div class="ai-card-list">
                        @forelse ($d['controles'] as $controle)
                            @php
                                $enRetard = $controle->actif && $controle->prochaine_echeance?->lt(today());
                                $couleurRisque = ['faible' => '#16a34a', 'moyen' => '#d97706', 'eleve' => '#ea580c', 'critique' => '#dc2626'][$controle->niveau_risque] ?? '#64748b';
                            @endphp
                            <article class="ai-card" style="--ai-accent:{{ $couleurRisque }}" wire:key="controle-{{ $controle->id }}">
                                <div class="ai-card-top">
                                    <h4 class="ai-card-title">{{ $controle->titre }}</h4>
                                    <div class="ai-badges">
                                        <span class="ai-badge {{ $enRetard ? 'is-danger' : '' }}">{{ $controle->actif ? ($enRetard ? 'En retard' : 'Actif') : 'Suspendu' }}</span>
                                        <span class="ai-badge">{{ $risques[$controle->niveau_risque] ?? $controle->niveau_risque }}</span>
                                    </div>
                                </div>
                                <p class="ai-card-copy">{{ $controle->objectif }}</p>
                                @if (! $d['peutModifier'])
                                    <p class="ai-card-copy"><strong>Compagnie :</strong> {{ $controle->compagnie?->nom ?? '—' }}</p>
                                @endif
                                <div class="ai-meta">
                                    <span><strong>Fréquence :</strong> {{ $frequences[$controle->frequence] ?? $controle->frequence }}</span>
                                    <span><strong>Responsable :</strong> {{ $controle->responsable?->getFilamentName() ?? 'Non attribué' }}</span>
                                    <span><strong>Prochaine échéance :</strong> {{ $controle->prochaine_echeance?->format('d/m/Y') ?? 'Non définie' }}</span>
                                </div>
                                @if ($d['peutModifier'])
                                    <div class="ai-actions">
                                        <button type="button" class="ai-action" wire:click="mountAction('modifierControle', { id: {{ $controle->id }} })">Modifier</button>
                                        <button type="button" class="ai-action" wire:click="basculerControle({{ $controle->id }})" wire:loading.attr="disabled">
                                            {{ $controle->actif ? 'Suspendre' : 'Réactiver' }}
                                        </button>
                                    </div>
                                @endif
                            </article>
                        @empty
                            <div class="ai-empty">
                                <x-filament::icon icon="heroicon-o-clipboard-document-list" />
                                <strong>Le plan de contrôle est encore vide</strong>
                                <span>Ajoutez un contrôle, définissez sa fréquence et désignez la personne chargée de le réaliser.</span>
                            </div>
                        @endforelse
                    </div>
                </section>

                <section class="ai-section" style="--ai-accent:#d97706">
                    <div class="ai-section-head">
                        <div class="ai-section-title">
                            <span class="ai-section-icon"><x-filament::icon icon="heroicon-o-flag" /></span>
                            <div>
                                <h3>Recommandations</h3>
                                <div class="ai-section-sub">Actions correctives et état d’avancement</div>
                            </div>
                        </div>
                        <span class="ai-count">{{ $d['recommandations']->count() }}</span>
                    </div>

                    <div class="ai-card-list">
                        @forelse ($d['recommandations'] as $recommandation)
                            @php
                                $enRetard = $recommandation->statut !== 'terminee' && $recommandation->echeance?->lt(today());
                                $couleurPriorite = ['basse' => '#64748b', 'normale' => '#0f766e', 'haute' => '#ea580c', 'critique' => '#dc2626'][$recommandation->priorite] ?? '#64748b';
                            @endphp
                            <article class="ai-card" style="--ai-accent:{{ $couleurPriorite }}" wire:key="recommandation-{{ $recommandation->id }}">
                                <div class="ai-card-top">
                                    <h4 class="ai-card-title">{{ $recommandation->titre }}</h4>
                                    <div class="ai-badges">
                                        <span class="ai-badge {{ $enRetard ? 'is-danger' : '' }}">{{ $enRetard ? 'En retard' : ($statuts[$recommandation->statut] ?? $recommandation->statut) }}</span>
                                        <span class="ai-badge">{{ $priorites[$recommandation->priorite] ?? $recommandation->priorite }}</span>
                                    </div>
                                </div>
                                <p class="ai-card-copy"><strong>Constat :</strong> {{ $recommandation->constat }}</p>
                                <p class="ai-card-copy"><strong>Action :</strong> {{ $recommandation->action_requise }}</p>
                                @if ($recommandation->controle)
                                    <p class="ai-card-copy"><strong>Contrôle lié :</strong> {{ $recommandation->controle->titre }}</p>
                                @endif
                                <div class="ai-meta">
                                    <span><strong>Responsable :</strong> {{ $recommandation->responsable?->getFilamentName() ?? 'Non attribué' }}</span>
                                    <span><strong>Échéance :</strong> {{ $recommandation->echeance?->format('d/m/Y') ?? 'Non définie' }}</span>
                                    @if (! $d['peutModifier'])
                                        <span><strong>Compagnie :</strong> {{ $recommandation->compagnie?->nom ?? '—' }}</span>
                                    @endif
                                    @if ($recommandation->resolution)
                                        <span><strong>Résolution :</strong> {{ $recommandation->resolution }}</span>
                                    @endif
                                </div>
                                @if ($d['peutModifier'])
                                    <div class="ai-actions">
                                        <button type="button" class="ai-action" wire:click="mountAction('modifierRecommandation', { id: {{ $recommandation->id }} })">Mettre à jour</button>
                                    </div>
                                @endif
                            </article>
                        @empty
                            <div class="ai-empty">
                                <x-filament::icon icon="heroicon-o-flag" />
                                <strong>Aucune recommandation à suivre</strong>
                                <span>Enregistrez une recommandation pour lui donner une priorité, un responsable et une échéance.</span>
                            </div>
                        @endforelse
                    </div>
                </section>
            </div>
        @endif
    </div>
</x-filament-panels::page>
