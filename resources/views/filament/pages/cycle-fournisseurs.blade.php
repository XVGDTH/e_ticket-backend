<x-filament-panels::page>
    <div class="cf-dashboard">
        <style>
            .cf-dashboard {
                --cf-ink: #0f172a;
                --cf-muted: #64748b;
                --cf-card: #fff;
                --cf-line: rgba(15, 23, 42, .09);
                --cf-soft: #f1f5f9;
                display: grid;
                gap: 1.15rem;
                color: var(--cf-ink);
            }
            .dark .cf-dashboard {
                --cf-ink: #f1f5f9;
                --cf-muted: #94a3b8;
                --cf-card: rgba(30, 41, 59, .82);
                --cf-line: rgba(255, 255, 255, .1);
                --cf-soft: rgba(255, 255, 255, .06);
            }
            .cf-hero {
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto;
                align-items: center;
                gap: 1.2rem;
                padding: clamp(1.3rem, 3vw, 2rem);
                border-radius: 1.5rem;
                color: #fff;
                background:
                    radial-gradient(600px 280px at 90% -10%, rgba(45, 212, 191, .38), transparent 60%),
                    linear-gradient(130deg, #0f172a, #164e63 70%, #0f766e);
                box-shadow: 0 24px 55px -38px rgba(15, 118, 110, .9);
            }
            .cf-eyebrow { font-size: .68rem; font-weight: 850; letter-spacing: .15em; text-transform: uppercase; opacity: .76; }
            .cf-hero h2 { margin: .4rem 0; font-size: clamp(1.45rem, 3vw, 2rem); font-weight: 850; letter-spacing: -.03em; }
            .cf-hero p { max-width: 48rem; margin: 0; font-size: .87rem; line-height: 1.55; opacity: .82; }
            .cf-hero-mark { display: grid; width: 4.5rem; height: 4.5rem; place-items: center; border: 1px solid rgba(255,255,255,.25); border-radius: 1.35rem; background: rgba(255,255,255,.12); }
            .cf-hero-mark svg { width: 2.2rem; height: 2.2rem; }
            .cf-company { max-width: 24rem; }
            .cf-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; }
            .cf-stat { position: relative; overflow: hidden; min-width: 0; padding: .95rem 1rem; border: 1px solid var(--cf-line); border-radius: 1.05rem; background: var(--cf-card); }
            .cf-stat::before { position: absolute; inset: 0 auto 0 0; width: 4px; background: var(--cf-accent, #0f766e); content: ""; }
            .cf-stat-label { color: var(--cf-muted); font-size: .65rem; font-weight: 850; letter-spacing: .08em; text-transform: uppercase; }
            .cf-stat-value { margin-top: .25rem; font-size: 1.6rem; font-weight: 850; line-height: 1.1; font-variant-numeric: tabular-nums; }
            .cf-section { min-width: 0; padding: 1.05rem; border: 1px solid var(--cf-line); border-radius: 1.3rem; background: var(--cf-card); }
            .cf-section-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .7rem; margin-bottom: .85rem; }
            .cf-section h3 { margin: 0; font-size: 1rem; font-weight: 850; }
            .cf-section-sub { margin-top: .15rem; color: var(--cf-muted); font-size: .74rem; }
            .cf-section-count { display: grid; min-width: 1.65rem; height: 1.65rem; place-items: center; padding: 0 .4rem; border-radius: 999px; color: #0f766e; background: rgba(13, 148, 136, .12); font-size: .7rem; font-weight: 850; }
            .cf-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .7rem; }
            .cf-card { display: grid; align-content: start; gap: .55rem; min-width: 0; padding: .9rem; border: 1px solid var(--cf-line); border-left: 4px solid var(--cf-accent, #0f766e); border-radius: .95rem; background: color-mix(in srgb, var(--cf-card) 94%, var(--cf-soft)); }
            .cf-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .5rem; }
            .cf-card-title { margin: 0; font-size: .86rem; font-weight: 850; line-height: 1.35; overflow-wrap: anywhere; }
            .cf-card-copy { margin: 0; color: var(--cf-muted); font-size: .73rem; line-height: 1.45; overflow-wrap: anywhere; }
            .cf-meta { display: grid; gap: .25rem; color: var(--cf-muted); font-size: .7rem; }
            .cf-badge { display: inline-flex; align-items: center; width: fit-content; padding: .17rem .52rem; border: 1px solid var(--cf-line); border-radius: 999px; color: var(--cf-muted); font-size: .65rem; font-weight: 800; white-space: nowrap; }
            .cf-badge.is-pending { border-color: rgba(245, 158, 11, .3); color: #b45309; background: rgba(245, 158, 11, .1); }
            .cf-badge.is-valid { border-color: rgba(13, 148, 136, .27); color: #0f766e; background: rgba(13, 148, 136, .09); }
            .dark .cf-badge.is-pending { color: #fbbf24; }
            .dark .cf-badge.is-valid { color: #5eead4; }
            .cf-lines { display: grid; gap: .3rem; padding-top: .45rem; border-top: 1px dashed var(--cf-line); color: var(--cf-muted); font-size: .7rem; }
            .cf-line { display: flex; justify-content: space-between; gap: .5rem; }
            .cf-line span:last-child { color: var(--cf-ink); font-weight: 750; text-align: right; }
            .cf-actions { display: flex; flex-wrap: wrap; gap: .4rem; padding-top: .45rem; border-top: 1px dashed var(--cf-line); }
            .cf-action { padding: .31rem .62rem; border: 1px solid var(--cf-line); border-radius: 999px; color: var(--cf-ink); background: transparent; font-size: .69rem; font-weight: 800; cursor: pointer; }
            .cf-action:hover { border-color: #0f766e; background: rgba(13, 148, 136, .08); }
            .cf-empty { display: grid; justify-items: center; gap: .35rem; padding: 1.25rem .8rem; border: 1px dashed var(--cf-line); border-radius: .9rem; color: var(--cf-muted); text-align: center; }
            .cf-empty strong { color: var(--cf-ink); font-size: .82rem; }
            .cf-empty span { font-size: .72rem; }
            .cf-note { padding: .8rem 1rem; border: 1px solid rgba(14, 165, 233, .22); border-radius: .9rem; color: var(--cf-muted); background: rgba(14, 165, 233, .06); font-size: .76rem; line-height: 1.5; }
            @media (max-width: 1000px) {
                .cf-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                .cf-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            }
            @media (max-width: 620px) {
                .cf-hero { grid-template-columns: 1fr; }
                .cf-hero-mark { display: none; }
                .cf-grid { grid-template-columns: 1fr; }
                .cf-section { padding: .8rem; }
            }
            @media (prefers-reduced-motion: reduce) {
                .cf-dashboard *, .cf-dashboard *::before, .cf-dashboard *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; }
            }
        </style>

        <section class="cf-hero">
            <div>
                <div class="cf-eyebrow">Achats · Réceptions · Règlements</div>
                <h2>Cycle fournisseur maîtrisé</h2>
                <p>Chaque étape conserve son auteur et son validateur. Une réception validée met les articles en stock; une facture ne peut être payée qu’après validation.</p>
            </div>
            <div class="cf-hero-mark">
                <x-filament::icon icon="heroicon-o-clipboard-document-check" />
            </div>
        </section>

        @if (auth()->user()->isSuperAdmin())
            <div class="cf-company">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="compagnieId">
                        @foreach ($d['compagnies'] as $id => $nom)
                            <option value="{{ $id }}">{{ $nom }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        @endif

        @if ((int) $this->compagnieId > 0 || ! auth()->user()->isSuperAdmin())
            <div class="cf-stats">
                <article class="cf-stat" style="--cf-accent:#0f766e">
                    <div class="cf-stat-label">Fournisseurs</div>
                    <div class="cf-stat-value">{{ $d['fournisseurs']->count() }}</div>
                </article>
                <article class="cf-stat" style="--cf-accent:#0284c7">
                    <div class="cf-stat-label">Articles suivis</div>
                    <div class="cf-stat-value">{{ $d['articles']->count() }}</div>
                </article>
                <article class="cf-stat" style="--cf-accent:#d97706">
                    <div class="cf-stat-label">Réceptions à valider</div>
                    <div class="cf-stat-value">{{ $d['receptions']->where('statut', 'en_attente')->count() }}</div>
                </article>
                <article class="cf-stat" style="--cf-accent:#7c3aed">
                    <div class="cf-stat-label">Paiements à valider</div>
                    <div class="cf-stat-value">{{ $d['paiementsEnAttente'] }}</div>
                </article>
            </div>

            <div class="cf-note">
                Le stock de cette version est suivi en quantités par compagnie, sans entrepôts ni valorisation financière. Les montants des factures et paiements sont saisis en FCFA; ils ne créent pas encore d’écriture comptable.
            </div>

            <section class="cf-section">
                <div class="cf-section-head">
                    <div>
                        <h3>Fournisseurs</h3>
                        <div class="cf-section-sub">Tiers de la compagnie {{ $d['nomCompagnie'] }}</div>
                    </div>
                    <span class="cf-section-count">{{ $d['fournisseurs']->count() }}</span>
                </div>
                @if ($d['fournisseurs']->isEmpty())
                    <div class="cf-empty"><strong>Aucun fournisseur</strong><span>Ajoutez un tiers pour commencer le cycle d’achat.</span></div>
                @else
                    <div class="cf-grid">
                        @foreach ($d['fournisseurs'] as $fournisseur)
                            <article class="cf-card" style="--cf-accent:#0f766e">
                                <div class="cf-card-head">
                                    <h4 class="cf-card-title">{{ $fournisseur->nom }}</h4>
                                    <span class="cf-badge {{ $fournisseur->actif ? 'is-valid' : '' }}">{{ $fournisseur->actif ? 'Actif' : 'Inactif' }}</span>
                                </div>
                                <div class="cf-meta">
                                    @if ($fournisseur->identifiant_fiscal)<span>Fiscal : {{ $fournisseur->identifiant_fiscal }}</span>@endif
                                    @if ($fournisseur->telephone)<span>{{ $fournisseur->telephone }}</span>@endif
                                    @if ($fournisseur->email)<span>{{ $fournisseur->email }}</span>@endif
                                    <span>Créé par {{ $fournisseur->cree_par_nom }}</span>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="cf-section">
                <div class="cf-section-head">
                    <div>
                        <h3>Stock par article</h3>
                        <div class="cf-section-sub">Les quantités évoluent uniquement lors de la validation d’une réception.</div>
                    </div>
                    <span class="cf-section-count">{{ $d['articles']->count() }}</span>
                </div>
                @if ($d['articles']->isEmpty())
                    <div class="cf-empty"><strong>Catalogue vide</strong><span>Créez un article avant d’enregistrer une réception de stock.</span></div>
                @else
                    <div class="cf-grid">
                        @foreach ($d['articles'] as $article)
                            <article class="cf-card" style="--cf-accent:#0284c7">
                                <div class="cf-card-head">
                                    <h4 class="cf-card-title">{{ $article->nom }}</h4>
                                    <span class="cf-badge is-valid">{{ number_format((float) $article->quantite_stock, 3, ',', ' ') }} {{ $article->unite }}</span>
                                </div>
                                <p class="cf-card-copy">{{ $article->reference ?: 'Sans référence' }}</p>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="cf-section">
                <div class="cf-section-head">
                    <div>
                        <h3>Mouvements de stock récents</h3>
                        <div class="cf-section-sub">Historique des entrées créées lors de la validation des réceptions.</div>
                    </div>
                    <span class="cf-section-count">{{ $d['mouvements']->count() }}</span>
                </div>
                @if ($d['mouvements']->isEmpty())
                    <div class="cf-empty"><strong>Aucun mouvement</strong><span>Les entrées apparaîtront ici après la validation d’une réception.</span></div>
                @else
                    <div class="cf-grid">
                        @foreach ($d['mouvements'] as $mouvement)
                            <article class="cf-card" style="--cf-accent:#0284c7">
                                <div class="cf-card-head">
                                    <h4 class="cf-card-title">{{ $mouvement->article->nom }}</h4>
                                    <span class="cf-badge is-valid">Entrée</span>
                                </div>
                                <div class="cf-meta">
                                    <span>{{ $mouvement->reception->numero }} · {{ $mouvement->cree_le->format('d/m/Y H:i') }}</span>
                                    <span>Validé par {{ $mouvement->cree_par_nom }}</span>
                                </div>
                                <div class="cf-lines">
                                    <div class="cf-line">
                                        <span>Quantité entrée</span>
                                        <span>{{ number_format((float) $mouvement->quantite, 3, ',', ' ') }} {{ $mouvement->article->unite }}</span>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="cf-section">
                <div class="cf-section-head">
                    <div>
                        <h3>Réceptions</h3>
                        <div class="cf-section-sub">Les entrées en stock restent bloquées tant que la réception n’est pas validée.</div>
                    </div>
                    <span class="cf-section-count">{{ $d['receptions']->count() }}</span>
                </div>
                @if ($d['receptions']->isEmpty())
                    <div class="cf-empty"><strong>Aucune réception</strong><span>Enregistrez les quantités reçues auprès d’un fournisseur.</span></div>
                @else
                    <div class="cf-grid">
                        @foreach ($d['receptions'] as $reception)
                            <article class="cf-card" style="--cf-accent:{{ $reception->statut === 'en_attente' ? '#d97706' : '#0f766e' }}">
                                <div class="cf-card-head">
                                    <h4 class="cf-card-title">{{ $reception->numero }}</h4>
                                    <span class="cf-badge {{ $reception->statut === 'en_attente' ? 'is-pending' : 'is-valid' }}">{{ $reception->statut === 'en_attente' ? 'À valider' : 'Validée' }}</span>
                                </div>
                                <div class="cf-meta">
                                    <span>{{ $reception->fournisseur->nom }} · {{ $reception->recu_le->format('d/m/Y') }}</span>
                                    <span>Enregistrée par {{ $reception->cree_par_nom }}</span>
                                    @if ($reception->valide_par_nom)<span>Validée par {{ $reception->valide_par_nom }}</span>@endif
                                </div>
                                <div class="cf-lines">
                                    @foreach ($reception->lignes as $ligne)
                                        <div class="cf-line">
                                            <span>{{ $ligne->article->nom }}</span>
                                            <span>{{ number_format((float) $ligne->quantite, 3, ',', ' ') }} {{ $ligne->article->unite }}</span>
                                        </div>
                                    @endforeach
                                </div>
                                @if ($reception->statut === 'en_attente')
                                    <div class="cf-actions">
                                        <button type="button" class="cf-action" wire:click="mountAction('validerReception', { id: {{ $reception->id }} })">Valider la réception</button>
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="cf-section">
                <div class="cf-section-head">
                    <div>
                        <h3>Factures et paiements</h3>
                        <div class="cf-section-sub">Les paiements partiels sont permis sans dépasser le solde réservé.</div>
                    </div>
                    <span class="cf-section-count">{{ $d['factures']->count() }}</span>
                </div>
                @if ($d['factures']->isEmpty())
                    <div class="cf-empty"><strong>Aucune facture</strong><span>Une facture peut être enregistrée après validation de sa réception.</span></div>
                @else
                    <div class="cf-grid">
                        @foreach ($d['factures'] as $facture)
                            <article class="cf-card" style="--cf-accent:{{ $facture->statut === 'en_attente' ? '#d97706' : ($facture->statut === 'reglee' ? '#0f766e' : '#7c3aed') }}">
                                <div class="cf-card-head">
                                    <h4 class="cf-card-title">{{ $facture->numero }}</h4>
                                    <span class="cf-badge {{ $facture->statut === 'en_attente' ? 'is-pending' : 'is-valid' }}">
                                        {{ ['en_attente' => 'À valider', 'validee' => 'Validée', 'partiellement_reglee' => 'Partiellement réglée', 'reglee' => 'Réglée'][$facture->statut] ?? $facture->statut }}
                                    </span>
                                </div>
                                <div class="cf-meta">
                                    <span>{{ $facture->fournisseur->nom }} · Réf. {{ $facture->reference_fournisseur }}</span>
                                    <span>Émise le {{ $facture->date_facture->format('d/m/Y') }} · {{ number_format($facture->montant, 0, ',', ' ') }} FCFA</span>
                                    <span>Enregistrée par {{ $facture->cree_par_nom }}</span>
                                    @if ($facture->valide_par_nom)<span>Validée par {{ $facture->valide_par_nom }}</span>@endif
                                    @if ($facture->piece_path)
                                        <a class="cf-action" href="{{ route('cycle-fournisseurs.piece', $facture->id) }}">Consulter la pièce justificative</a>
                                    @endif
                                </div>
                                @if ($facture->paiements->isNotEmpty())
                                    <div class="cf-lines">
                                        @foreach ($facture->paiements as $paiement)
                                            <div class="cf-line">
                                                <span>{{ $paiement->numero }} · {{ $paiement->statut === 'valide' ? 'Validé' : 'À valider' }}</span>
                                                <span>{{ number_format($paiement->montant, 0, ',', ' ') }} FCFA</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="cf-actions">
                                    @if ($facture->statut === 'en_attente')
                                        <button type="button" class="cf-action" wire:click="mountAction('validerFacture', { id: {{ $facture->id }} })">Valider la facture</button>
                                    @endif
                                    @if (in_array($facture->statut, ['validee', 'partiellement_reglee'], true))
                                        <button type="button" class="cf-action" wire:click="mountAction('creerPaiement')">Saisir un paiement</button>
                                    @endif
                                </div>
                                @foreach ($facture->paiements->where('statut', 'en_attente') as $paiement)
                                    <div class="cf-actions">
                                        <button type="button" class="cf-action" wire:click="mountAction('validerPaiement', { id: {{ $paiement->id }} })">Valider {{ $paiement->numero }}</button>
                                    </div>
                                @endforeach
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        @else
            <section class="cf-section">
                <div class="cf-section-head">
                    <div>
                        <h3>Vue globale des fournisseurs et des stocks</h3>
                        <div class="cf-section-sub">Lecture seule · les opérations d’achat restent rattachées à une compagnie.</div>
                    </div>
                </div>
                @if ($d['parCompagnie']->isEmpty())
                    <div class="cf-empty"><strong>Aucune compagnie</strong><span>Les données apparaîtront lorsqu’une compagnie sera enregistrée.</span></div>
                @else
                    <div class="cf-grid">
                        @foreach ($d['parCompagnie'] as $compagnie)
                            <article class="cf-card" style="--cf-accent:#0f766e" wire:key="cycle-compagnie-{{ $compagnie['id'] }}">
                                <div class="cf-card-head">
                                    <h4 class="cf-card-title">{{ $compagnie['nom'] }}</h4>
                                    <span class="cf-badge is-valid">{{ $compagnie['fournisseurs'] }} fournisseur(s)</span>
                                </div>
                                <div class="cf-lines">
                                    <div class="cf-line"><span>Articles en stock</span><strong>{{ $compagnie['articles'] }}</strong></div>
                                    <div class="cf-line"><span>Réceptions</span><strong>{{ $compagnie['receptions'] }}</strong></div>
                                    <div class="cf-line"><span>Factures fournisseurs</span><strong>{{ $compagnie['factures'] }}</strong></div>
                                    <div class="cf-line"><span>Paiements à valider</span><strong>{{ $compagnie['paiementsEnAttente'] }}</strong></div>
                                </div>
                                <div class="cf-actions">
                                    <button type="button" class="cf-action" wire:click="$set('compagnieId', '{{ $compagnie['id'] }}')">Consulter cette compagnie</button>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
    </div>
</x-filament-panels::page>
