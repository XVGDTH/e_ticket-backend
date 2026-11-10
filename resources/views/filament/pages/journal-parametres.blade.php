<x-filament-panels::page>
    @include('filament.partials.finance-styles')

    <div class="jp-dashboard">
        <style>
            .jp-dashboard {
                --jp-ink: #0f172a;
                --jp-muted: #64748b;
                --jp-card: #fff;
                --jp-line: rgba(15, 23, 42, .09);
                --jp-soft: #f1f5f9;
                display: grid;
                gap: 1.15rem;
                color: var(--jp-ink);
            }
            .dark .jp-dashboard {
                --jp-ink: #f1f5f9;
                --jp-muted: #94a3b8;
                --jp-card: rgba(30, 41, 59, .78);
                --jp-line: rgba(255, 255, 255, .1);
                --jp-soft: rgba(255, 255, 255, .07);
            }
            .jp-hero {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                padding: clamp(1.2rem, 3vw, 1.8rem);
                border-radius: 1.4rem;
                color: #fff;
                background:
                    radial-gradient(600px 260px at 95% -35%, rgba(45, 212, 191, .4), transparent 60%),
                    linear-gradient(125deg, #0f172a, #134e4a 72%, #0f766e);
                box-shadow: 0 22px 50px -35px rgba(15, 118, 110, .8);
            }
            .jp-hero h2 { margin: .35rem 0; font-size: clamp(1.25rem, 2.5vw, 1.8rem); font-weight: 850; letter-spacing: -.025em; }
            .jp-hero p { max-width: 46rem; margin: 0; font-size: .84rem; line-height: 1.55; opacity: .8; }
            .jp-hero-mark { display: grid; width: 3.8rem; height: 3.8rem; flex: none; place-items: center; border: 1px solid rgba(255,255,255,.25); border-radius: 1.2rem; background: rgba(255,255,255,.12); }
            .jp-hero-mark svg { width: 2rem; height: 2rem; }
            .jp-toolbar { display: flex; flex-wrap: wrap; align-items: end; justify-content: space-between; gap: .8rem; }
            .jp-filter { display: grid; gap: .35rem; min-width: min(100%, 19rem); color: var(--jp-muted); font-size: .72rem; font-weight: 750; }
            .jp-filter select { width: 100%; padding: .65rem .8rem; border: 1px solid var(--jp-line); border-radius: .8rem; color: var(--jp-ink); background: var(--jp-card); }
            .jp-stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
            .jp-stat { padding: .9rem 1rem; border: 1px solid var(--jp-line); border-radius: 1rem; background: var(--jp-card); }
            .jp-stat-label { color: var(--jp-muted); font-size: .68rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
            .jp-stat-value { margin-top: .2rem; font-size: 1.55rem; font-weight: 850; line-height: 1.1; font-variant-numeric: tabular-nums; }
            .jp-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; gap: .85rem; }
            .jp-entry { min-width: 0; padding: 1rem; border: 1px solid var(--jp-line); border-left: 4px solid var(--jp-accent, #0f766e); border-radius: 1.1rem; background: var(--jp-card); }
            .jp-entry-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .65rem; }
            .jp-entry h3 { margin: .25rem 0 0; font-size: .9rem; font-weight: 850; line-height: 1.35; }
            .jp-entry time { flex: none; color: var(--jp-muted); font-size: .7rem; }
            .jp-badge { display: inline-flex; padding: .2rem .55rem; border: 1px solid var(--jp-line); border-radius: 999px; color: var(--jp-muted); font-size: .65rem; font-weight: 800; }
            .jp-badge.is-creation { border-color: rgba(16, 185, 129, .25); color: #047857; background: rgba(16, 185, 129, .08); }
            .jp-badge.is-suppression { border-color: rgba(239, 68, 68, .25); color: #dc2626; background: rgba(239, 68, 68, .08); }
            .dark .jp-badge.is-creation { color: #6ee7b7; }
            .dark .jp-badge.is-suppression { color: #fca5a5; }
            .jp-meta { display: flex; flex-wrap: wrap; gap: .35rem .85rem; margin-top: .65rem; color: var(--jp-muted); font-size: .72rem; }
            .jp-values { display: grid; gap: .5rem; margin-top: .8rem; }
            .jp-change { display: grid; grid-template-columns: minmax(6rem, .75fr) minmax(0, 1.5fr); gap: .5rem; align-items: start; padding: .55rem .65rem; border-radius: .7rem; background: var(--jp-soft); font-size: .73rem; }
            .jp-change-label { color: var(--jp-muted); font-weight: 750; }
            .jp-change-value { min-width: 0; overflow-wrap: anywhere; font-variant-numeric: tabular-nums; }
            .jp-old { color: var(--jp-muted); text-decoration: line-through; }
            .jp-arrow { padding: 0 .25rem; color: var(--jp-muted); }
            .jp-new { font-weight: 800; }
            .jp-empty { display: grid; justify-items: center; gap: .4rem; padding: 2rem 1rem; border: 1px dashed var(--jp-line); border-radius: 1rem; color: var(--jp-muted); text-align: center; }
            .jp-empty strong { color: var(--jp-ink); }
            @media (max-width: 800px) { .jp-grid { grid-template-columns: 1fr; } }
            @media (max-width: 540px) {
                .jp-hero-mark { display: none; }
                .jp-entry-head { display: grid; }
                .jp-change { grid-template-columns: 1fr; gap: .2rem; }
            }
        </style>

        <section class="jp-hero">
            <div>
                <h2>Une trace claire de chaque changement</h2>
                <p>Les événements sont enregistrés avec leur auteur et leur contexte. Ce journal est consultable en lecture seule et ne peut pas être modifié depuis l’application.</p>
            </div>
            <div class="jp-hero-mark">
                <x-filament::icon icon="heroicon-o-shield-check" />
            </div>
        </section>

        <div class="jp-toolbar">
            @if (auth()->user()->isSuperAdmin())
                <label class="jp-filter">
                    <span>Filtrer par compagnie</span>
                    <select wire:model.live="compagnieId">
                        <option value="">Toutes les compagnies</option>
                        @foreach ($compagnies as $id => $nom)
                            <option value="{{ $id }}">{{ $nom }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <div class="jp-stats">
                <div class="jp-stat">
                    <div class="jp-stat-label">Événements au total</div>
                    <div class="jp-stat-value">{{ number_format($total, 0, ',', ' ') }}</div>
                </div>
                <div class="jp-stat">
                    <div class="jp-stat-label">Aujourd’hui</div>
                    <div class="jp-stat-value">{{ number_format($aujourdHui, 0, ',', ' ') }}</div>
                </div>
            </div>
        </div>

        @if ($journal->isEmpty())
            <div class="jp-empty">
                <strong>Aucun changement enregistré</strong>
                <span>Les prochains changements de paramètres apparaîtront ici automatiquement.</span>
            </div>
        @else
            <div class="jp-grid">
                @foreach ($journal as $entree)
                    <article @class([
                        'jp-entry',
                        'jp-entry--creation' => $entree->evenement === 'creation',
                        'jp-entry--suppression' => $entree->evenement === 'suppression',
                    ]) @style([
                        '--jp-accent: #059669' => $entree->evenement === 'creation',
                        '--jp-accent: #dc2626' => $entree->evenement === 'suppression',
                    ])>
                        <div class="jp-entry-head">
                            <div>
                                <span @class([
                                    'jp-badge',
                                    'is-creation' => $entree->evenement === 'creation',
                                    'is-suppression' => $entree->evenement === 'suppression',
                                ])>{{ $entree->libelleEvenement() }}</span>
                                <h3>{{ $entree->libelleEntite() }}{{ $entree->entite_id ? ' · #'.$entree->entite_id : '' }}</h3>
                            </div>
                            <time datetime="{{ $entree->enregistre_le->toIso8601String() }}">
                                {{ $entree->enregistre_le->format('d/m/Y à H:i') }}
                            </time>
                        </div>

                        <div class="jp-meta">
                            <span>Par {{ $entree->acteur_nom }}</span>
                            @if ($entree->compagnie_nom)
                                <span>{{ $entree->compagnie_nom }}</span>
                            @elseif ($entree->entite === 'frais_regles')
                                <span>Tarif général · toutes les compagnies</span>
                            @endif
                            @if ($entree->cible_utilisateur_nom)
                                <span>Utilisateur concerné : {{ $entree->cible_utilisateur_nom }}</span>
                            @endif
                        </div>

                        <div class="jp-values">
                            @foreach ($entree->valeurs as $changement)
                                <div class="jp-change">
                                    <span class="jp-change-label">{{ $changement['libelle'] ?? 'Paramètre' }}</span>
                                    <span class="jp-change-value">
                                        <span class="jp-old">{{ $entree->valeurLisible($changement['ancienne'] ?? null) }}</span>
                                        <span class="jp-arrow">→</span>
                                        <span class="jp-new">{{ $entree->valeurLisible($changement['nouvelle'] ?? null) }}</span>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
            @if ($total > $journal->count())
                <p class="text-center text-sm text-gray-500">Les 60 événements les plus récents sont affichés.</p>
            @endif
        @endif
    </div>
</x-filament-panels::page>
