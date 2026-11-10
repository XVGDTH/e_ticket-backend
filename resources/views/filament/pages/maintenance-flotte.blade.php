<x-filament-panels::page>
    <style>
        .mf { --mf-ink:#0f172a; --mf-muted:#64748b; --mf-line:rgba(15,23,42,.1); --mf-card:#fff; display:grid; gap:1rem; color:var(--mf-ink); }
        .dark .mf { --mf-ink:#f1f5f9; --mf-muted:#94a3b8; --mf-line:rgba(255,255,255,.1); --mf-card:rgba(30,41,59,.8); }
        .mf-toolbar { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.65rem; }
        .mf-filter, .mf-actions { display:flex; flex-wrap:wrap; align-items:center; gap:.55rem; }
        .mf-filter { min-width:220px; }
        .mf-link { display:inline-flex; align-items:center; justify-content:center; gap:.4rem; padding:.55rem .8rem; border:1px solid var(--mf-line); border-radius:.75rem; color:var(--mf-ink); background:var(--mf-card); font-size:.75rem; font-weight:800; text-decoration:none; }
        .mf-link:hover { border-color:#0f766e; color:#0f766e; }
        .mf-hero { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:1rem; padding:clamp(1.2rem,3vw,1.8rem); border-radius:1.35rem; color:#fff; background:radial-gradient(520px 260px at 90% 0%,rgba(45,212,191,.32),transparent 62%),linear-gradient(130deg,#0f172a,#134e4a 72%,#0f766e); }
        .mf-eyebrow { font-size:.67rem; font-weight:850; letter-spacing:.14em; text-transform:uppercase; opacity:.72; }
        .mf-hero h2 { margin:.35rem 0; font-size:clamp(1.35rem,3vw,1.9rem); font-weight:850; letter-spacing:-.03em; }
        .mf-hero p { margin:0; color:rgba(255,255,255,.78); font-size:.83rem; }
        .mf-period { padding:.45rem .7rem; border:1px solid rgba(255,255,255,.25); border-radius:999px; background:rgba(255,255,255,.1); font-size:.72rem; font-weight:800; }
        .mf-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:.75rem; }
        .mf-kpi, .mf-panel { min-width:0; border:1px solid var(--mf-line); border-radius:1rem; background:var(--mf-card); }
        .mf-kpi { padding:1rem; }
        .mf-kpi-label { color:var(--mf-muted); font-size:.66rem; font-weight:850; letter-spacing:.08em; text-transform:uppercase; }
        .mf-kpi-value { margin:.25rem 0; font-size:1.5rem; font-weight:900; letter-spacing:-.03em; font-variant-numeric:tabular-nums; }
        .mf-kpi-note { color:var(--mf-muted); font-size:.7rem; }
        .mf-layout { display:grid; grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr); gap:.8rem; }
        .mf-panel { padding:1rem; }
        .mf-panel-head { display:flex; align-items:flex-start; justify-content:space-between; gap:.6rem; margin-bottom:.8rem; }
        .mf-panel-title { margin:0; font-size:.95rem; font-weight:850; }
        .mf-panel-subtitle { margin:.18rem 0 0; color:var(--mf-muted); font-size:.72rem; }
        .mf-list { display:grid; gap:.55rem; }
        .mf-row { display:flex; align-items:flex-start; justify-content:space-between; gap:.7rem; padding:.75rem; border:1px solid var(--mf-line); border-radius:.8rem; }
        .mf-row-main { min-width:0; }
        .mf-row-title { margin:0; font-size:.79rem; font-weight:850; overflow-wrap:anywhere; }
        .mf-row-meta { margin:.18rem 0 0; color:var(--mf-muted); font-size:.69rem; line-height:1.45; overflow-wrap:anywhere; }
        .mf-amount { flex:none; font-size:.8rem; font-weight:900; text-align:right; font-variant-numeric:tabular-nums; }
        .mf-badge { display:inline-flex; align-items:center; padding:.18rem .48rem; border-radius:999px; color:#0f766e; background:rgba(20,184,166,.12); font-size:.63rem; font-weight:850; white-space:nowrap; }
        .mf-badge.warning { color:#b45309; background:rgba(245,158,11,.14); }
        .mf-badge.danger { color:#b91c1c; background:rgba(239,68,68,.12); }
        .mf-empty { padding:1rem; border:1px dashed var(--mf-line); border-radius:.8rem; color:var(--mf-muted); font-size:.75rem; text-align:center; }
        @media (max-width:760px) { .mf-layout { grid-template-columns:1fr; } }
        @media (max-width:520px) { .mf-row { flex-direction:column; } .mf-amount { text-align:left; } }
    </style>

    <div class="mf">
        <div class="mf-toolbar">
            @if (count($compagnies) > 1)
                <div class="mf-filter">
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="compagnieId" aria-label="Filtrer par compagnie">
                            @foreach ($compagnies as $id => $nom)
                                <option value="{{ $id }}">{{ $nom }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            @endif
            @if ($afficherActionsFlotte)
                <div class="mf-actions">
                    <a class="mf-link" href="{{ $urlVehicules }}">Voir les cars</a>
                    <a class="mf-link" href="{{ $urlDepenses }}">Saisir une dépense</a>
                </div>
            @endif
        </div>

        <section class="mf-hero">
            <div>
                <div class="mf-eyebrow">Suivi opérationnel · {{ $nomCompagnie }}</div>
                <h2>Maintenance et carburant</h2>
                <p>Surveillez les immobilisations, les échéances de vidange et les dépenses de la flotte.</p>
            </div>
            <span class="mf-period">Dépenses du {{ $periode }}</span>
        </section>

        <div class="mf-kpis">
            <article class="mf-kpi">
                <div class="mf-kpi-label">Cars suivis</div>
                <div class="mf-kpi-value">{{ number_format($statistiques['vehicules'], 0, ',', ' ') }}</div>
                <div class="mf-kpi-note">Dans le périmètre sélectionné</div>
            </article>
            <article class="mf-kpi">
                <div class="mf-kpi-label">À l’arrêt</div>
                <div class="mf-kpi-value">{{ number_format($statistiques['immobilises'], 0, ',', ' ') }}</div>
                <div class="mf-kpi-note">En maintenance ou en panne</div>
            </article>
            <article class="mf-kpi">
                <div class="mf-kpi-label">Entretien ce mois</div>
                <div class="mf-kpi-value">{{ number_format($statistiques['depenses_maintenance'], 0, ',', ' ') }} <small>FCFA</small></div>
                <div class="mf-kpi-note">Entretien, réparation, pneus et lavage</div>
            </article>
            <article class="mf-kpi">
                <div class="mf-kpi-label">Carburant ce mois</div>
                <div class="mf-kpi-value">{{ number_format($statistiques['depenses_carburant'], 0, ',', ' ') }} <small>FCFA</small></div>
                <div class="mf-kpi-note">Dépenses de carburant enregistrées</div>
            </article>
        </div>

        <div class="mf-layout">
            <section class="mf-panel">
                <div class="mf-panel-head">
                    <div>
                        <h3 class="mf-panel-title">Cars à surveiller</h3>
                        <p class="mf-panel-subtitle">Immobilisation, vidange proche ou document à renouveler</p>
                    </div>
                    <span class="mf-badge {{ $vehiculesAlerte->isEmpty() ? '' : 'warning' }}">{{ $vehiculesAlerte->count() }} alerte(s)</span>
                </div>
                @if ($vehiculesAlerte->isEmpty())
                    <div class="mf-empty">Aucune alerte de maintenance pour cette flotte.</div>
                @else
                    <div class="mf-list">
                        @foreach ($vehiculesAlerte as $vehicule)
                            @php
                                $kmRestants = $vehicule->km_prochaine_vidange === null
                                    ? null
                                    : (int) $vehicule->km_prochaine_vidange - (int) $vehicule->km_actuel;
                                $immobilise = in_array($vehicule->statut, ['maintenance', 'panne'], true);
                            @endphp
                            <article class="mf-row" wire:key="maintenance-vehicule-{{ $vehicule->id }}">
                                <div class="mf-row-main">
                                    <h4 class="mf-row-title">{{ $vehicule->libelle() }}</h4>
                                    <p class="mf-row-meta">
                                        {{ \App\Models\Vehicule::STATUTS[$vehicule->statut] ?? $vehicule->statut }}
                                        · {{ number_format((int) $vehicule->km_actuel, 0, ',', ' ') }} km
                                        @if ($kmRestants !== null)
                                            · vidange {{ $kmRestants <= 0 ? 'dépassée' : 'dans '.number_format($kmRestants, 0, ',', ' ').' km' }}
                                        @endif
                                        @if ($vehicule->etatDocuments() !== 'A jour' && $vehicule->etatDocuments() !== 'Non renseignés')
                                            · documents {{ mb_strtolower($vehicule->etatDocuments()) }}
                                        @endif
                                    </p>
                                </div>
                                <span class="mf-badge {{ $immobilise || ($kmRestants !== null && $kmRestants <= 0) ? 'danger' : 'warning' }}">
                                    {{ $immobilise ? 'Intervention requise' : 'À vérifier' }}
                                </span>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="mf-panel">
                <div class="mf-panel-head">
                    <div>
                        <h3 class="mf-panel-title">Dernières dépenses</h3>
                        <p class="mf-panel-subtitle">Carburant et interventions récemment enregistrés</p>
                    </div>
                </div>
                @if ($depensesRecentes->isEmpty())
                    <div class="mf-empty">Aucune dépense enregistrée. Ajoutez vos premières opérations pour suivre les coûts.</div>
                @else
                    <div class="mf-list">
                        @foreach ($depensesRecentes as $depense)
                            <article class="mf-row" wire:key="maintenance-depense-{{ $depense->id }}">
                                <div class="mf-row-main">
                                    <h4 class="mf-row-title">{{ \App\Models\DepenseVehicule::CATEGORIES[$depense->categorie] ?? ucfirst($depense->categorie) }}</h4>
                                    <p class="mf-row-meta">
                                        {{ $depense->vehicule?->libelle() ?? 'Car indisponible' }}
                                        · {{ $depense->date_depense->format('d/m/Y') }}
                                        @if ($depense->litres) · {{ number_format((float) $depense->litres, 1, ',', ' ') }} L @endif
                                        @if ($depense->fournisseur) · {{ $depense->fournisseur }} @endif
                                    </p>
                                </div>
                                <div class="mf-amount">{{ number_format((int) $depense->montant, 0, ',', ' ') }} FCFA</div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-filament-panels::page>
