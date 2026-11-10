<x-filament-panels::page>
    @include('filament.partials.finance-styles')

    @php
        $d = $this->donnees();
        $cr = $d['resultat'];
        $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
        $onglets = ['resultat' => 'Compte de résultat', 'bilan' => 'Bilan', 'balance' => 'Balance', 'grand_livre' => 'Grand livre', 'analytique' => 'Analytique'];
    @endphp

    <div class="rf rf-accounting">
        <div class="rf-hero cpt-hero">
            <div class="cpt-hero-head">
                <div>
                    <div class="rf-eyebrow">Espace financier · SYSCOHADA</div>
                    <h1>Comptabilité & pilotage</h1>
                    <p>Les états sont calculés à partir des écritures comptabilisées de l’exercice sélectionné.</p>
                </div>
                <div class="cpt-period-badge">
                    <span class="cpt-status-dot {{ $d['exercice'] ? '' : 'is-inactive' }}"></span>
                    <span>{{ $d['exercice'] ? $d['exercice']->libelle : 'Aucun exercice sélectionné' }}</span>
                </div>
            </div>
            <div class="cpt-hero-filters">
                @if (auth()->user()->isSuperAdmin())
                    <label>
                        <span>Compagnie</span>
                        <select wire:model.live="compagnieId" class="rf-select">
                            @foreach ($d['compagnies'] as $id => $nom)
                                <option value="{{ $id }}">{{ $nom }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <label>
                    <span>Exercice</span>
                    <select wire:model.live="exerciceId" class="rf-select">
                        @foreach ($d['exercices'] as $id => $libelle)
                            <option value="{{ $id }}">{{ $libelle }}</option>
                        @endforeach
                    </select>
                </label>
                @if ($d['exercice'])
                    <span class="cpt-date-range">
                        {{ $d['exercice']->date_debut->format('d/m/Y') }}
                        <span aria-hidden="true">→</span>
                        {{ $d['exercice']->date_fin->format('d/m/Y') }}
                    </span>
                @endif
            </div>
        </div>

        <nav class="cpt-shortcuts" aria-label="Accès rapides à la comptabilité">
            <a class="cpt-shortcut" href="{{ route('filament.admin.resources.ecritures.index') }}">
                <span class="cpt-shortcut-icon"><x-heroicon-o-pencil-square /></span>
                <span><strong>Écritures comptables</strong><small>Saisir, contrôler et consulter les écritures</small></span>
                <x-heroicon-o-arrow-up-right class="cpt-shortcut-arrow" />
            </a>
            <a class="cpt-shortcut" href="{{ route('filament.admin.resources.comptes-comptables.compte-comptables.index') }}">
                <span class="cpt-shortcut-icon"><x-heroicon-o-list-bullet /></span>
                <span><strong>Plan comptable</strong><small>Gérer les comptes SYSCOHADA de la compagnie</small></span>
                <x-heroicon-o-arrow-up-right class="cpt-shortcut-arrow" />
            </a>
            <div class="cpt-shortcut cpt-shortcut-current" aria-current="page">
                <span class="cpt-shortcut-icon"><x-heroicon-o-chart-bar-square /></span>
                <span><strong>États financiers</strong><small>Résultat, bilan, balance et analytique</small></span>
                <span class="cpt-current-label">Vous êtes ici</span>
            </div>
        </nav>

        <div class="rf-bar cpt-report-nav" aria-label="États financiers">
            @foreach ($onglets as $cle => $libelle)
                <button type="button" wire:click="$set('onglet', '{{ $cle }}')" class="rf-pill {{ $onglet === $cle ? 'is-on' : '' }}" aria-pressed="{{ $onglet === $cle ? 'true' : 'false' }}">{{ $libelle }}</button>
            @endforeach
        </div>

        <div class="rf-hero" style="background:radial-gradient(800px 380px at 92% -25%,rgba(251,191,36,.5),transparent 60%),linear-gradient(135deg,#1e1b4b 0%,#3730a3 58%,#4f46e5 100%)">
            <div class="rf-hero-grid">
                <div>
                    <div class="rf-eyebrow">Résultat net · {{ $d['nomCompagnie'] }}</div>
                    <div class="rf-big">
                        @if ($d['exercice'])
                            <span
                                wire:key="res-{{ $cr['resultat'] }}"
                                x-data="{ v: 0, cible: {{ (int) $cr['resultat'] }} }"
                                x-init="const s = performance.now(); const tick = (now) => { const k = Math.min(1, (now - s) / 1000); v = Math.round(cible * (1 - Math.pow(1 - k, 3))); if (k < 1) requestAnimationFrame(tick); }; requestAnimationFrame(tick)"
                                x-text="v.toLocaleString('fr-FR')">{{ $fmt($cr['resultat']) }}</span><small>FCFA</small>
                        @else
                            <span>—</span><small>FCFA</small>
                        @endif
                    </div>
                    <div class="rf-chips">
                        <span class="rf-chip">{{ $d['exercice'] ? ($cr['resultat'] >= 0 ? 'Bénéfice' : 'Perte')." de l'exercice" : 'Aucun résultat disponible' }}</span>
                        <span class="rf-chip">{{ $d['exercice'] ? 'Écritures comptabilisées' : 'Aucun exercice disponible' }}</span>
                    </div>
                </div>
                <div class="cpt-hero-stat-grid">
                    @if ($d['exercice'])
                        @foreach ([['Chiffre d\'affaires', $cr['chiffre_affaires']], ['Valeur ajoutée', $cr['valeur_ajoutee']], ['Excédent brut', $cr['ebe']]] as [$lib, $val])
                            <div class="cpt-hero-stat">
                                <div>{{ $lib }}</div>
                                <strong>{{ $fmt($val) }} <small>FCFA</small></strong>
                            </div>
                        @endforeach
                    @else
                        <div class="cpt-hero-empty">Sélectionnez un exercice pour afficher les indicateurs financiers.</div>
                    @endif
                </div>
            </div>
        </div>

        @if ($d['brouillons'] > 0)
            <div class="rf-note" role="status"><strong>{{ $d['brouillons'] }} écriture(s) en brouillon.</strong> Elles ne figurent pas dans ces états; vérifiez-les puis comptabilisez-les si elles sont prêtes.</div>
        @endif

        @if (! $d['exercice'])
            <div class="rf-note">Aucun exercice n'est ouvert pour cette compagnie.</div>

        @elseif ($onglet === 'resultat')
            <div class="rf-card">
                <div class="cpt-section-heading">
                    <div><span class="cpt-overline">Performance de la période</span><h2>Compte de résultat</h2></div>
                    <span class="cpt-section-caption">Produits positifs · Charges négatives</span>
                </div>
                <div class="cpt-statement-list">
                    @foreach ($cr['lignes'] as $l)
                        <div class="cpt-statement-line {{ $l['solde'] ? 'is-total' : '' }}">
                            <span>{{ $l['libelle'] }}</span>
                            <strong class="{{ $l['montant'] < 0 ? 'is-negative' : '' }}">{{ $fmt($l['montant']) }} <small>FCFA</small></strong>
                        </div>
                    @endforeach
                </div>
            </div>

        @elseif ($onglet === 'bilan')
            @php $b = $d['bilan']; @endphp
            @if (! $b['equilibre'])
                <div class="rf-note">Le bilan présente un écart de {{ $fmt($b['ecart']) }} FCFA entre l'actif et le passif : un compte n'est pas rattaché à un poste. Contrôlez le plan comptable.</div>
            @endif
            <div class="rf-two">
                <div class="rf-card">
                    <div class="cpt-section-heading"><div><span class="cpt-overline">Situation patrimoniale</span><h2>Actif</h2></div></div>
                    <div class="cpt-report-cards">
                        @foreach ($b['actif'] as $l)
                            <article class="cpt-report-card {{ $l['total'] ? 'is-total' : '' }}">
                                <strong class="cpt-report-card-title">{{ $l['libelle'] }}</strong>
                                <div class="cpt-metric-grid">
                                    <span><small>Brut</small><strong>{{ $l['brut'] === null ? '—' : $fmt($l['brut']) }}</strong></span>
                                    <span><small>Amort.</small><strong>{{ $l['amort'] === null ? '—' : $fmt($l['amort']) }}</strong></span>
                                    <span><small>Net</small><strong>{{ $fmt($l['net']) }}</strong></span>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
                <div class="rf-card">
                    <div class="cpt-section-heading"><div><span class="cpt-overline">Situation patrimoniale</span><h2>Passif</h2></div></div>
                    <div class="cpt-report-cards">
                        @foreach ($b['passif'] as $l)
                            <article class="cpt-report-card {{ $l['total'] ? 'is-total' : '' }}">
                                <strong class="cpt-report-card-title">{{ $l['libelle'] }}</strong>
                                <div class="cpt-metric-grid cpt-single-metric">
                                    <span><small>Montant</small><strong>{{ $fmt($l['net']) }} <small>FCFA</small></strong></span>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </div>

        @elseif ($onglet === 'balance')
            <div class="rf-card">
                <div class="cpt-section-heading"><div><span class="cpt-overline">Contrôle des soldes</span><h2>Balance générale</h2></div></div>
                <div class="cpt-report-cards cpt-account-grid cpt-scroll">
                    @forelse ($d['balance'] as $l)
                        <article class="cpt-report-card {{ $l[0] === 'TOTAL' ? 'is-total' : '' }}">
                            <div class="cpt-account-heading">
                                <span class="cpt-account-number">{{ $l[0] }}</span>
                                <strong class="cpt-report-card-title">{{ $l[1] }}</strong>
                            </div>
                            <div class="cpt-metric-grid">
                                <span><small>Débit</small><strong>{{ $l[2] ? $fmt($l[2]) : '—' }}</strong></span>
                                <span><small>Crédit</small><strong>{{ $l[3] ? $fmt($l[3]) : '—' }}</strong></span>
                                <span><small>Solde débiteur</small><strong>{{ $l[4] ? $fmt($l[4]) : '—' }}</strong></span>
                                <span><small>Solde créditeur</small><strong>{{ $l[5] ? $fmt($l[5]) : '—' }}</strong></span>
                            </div>
                        </article>
                    @empty
                        <div class="cpt-empty-state"><x-heroicon-o-document-magnifying-glass /><strong>Aucune écriture comptabilisée</strong><span>La balance apparaîtra dès qu’une écriture de cet exercice sera comptabilisée.</span></div>
                    @endforelse
                </div>
            </div>

        @elseif ($onglet === 'grand_livre')
            <div class="rf-card">
                <div class="cpt-section-heading"><div><span class="cpt-overline">Mouvements détaillés</span><h2>Grand livre</h2></div></div>
                <div class="rf-bar cpt-account-filter">
                    <select wire:model.live="compteId" class="rf-select" style="max-width:480px">
                        <option value="">Choisissez un compte…</option>
                        @foreach ($d['comptes'] as $id => $libelle)
                            <option value="{{ $id }}">{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($compteId === '')
                    <div class="cpt-empty-state"><x-heroicon-o-magnifying-glass /><strong>Sélectionnez un compte</strong><span>La liste ne contient que les comptes avec des mouvements comptabilisés sur cet exercice.</span></div>
                @else
                    <div class="cpt-report-cards cpt-scroll">
                        @forelse ($d['grandLivre'] as $l)
                            <article class="cpt-report-card cpt-ledger-card">
                                <div class="cpt-ledger-heading">
                                    <span class="cpt-date-tag">{{ $l[0] }}</span>
                                    <span class="cpt-account-number">{{ $l[1] }}</span>
                                </div>
                                <strong class="cpt-report-card-title">{{ $l[2] }}</strong>
                                <div class="cpt-metric-grid">
                                    <span><small>Débit</small><strong>{{ $l[3] ? $fmt($l[3]) : '—' }}</strong></span>
                                    <span><small>Crédit</small><strong>{{ $l[4] ? $fmt($l[4]) : '—' }}</strong></span>
                                    <span><small>Solde cumulé</small><strong>{{ $fmt($l[5]) }} <small>FCFA</small></strong></span>
                                </div>
                            </article>
                        @empty
                            <div class="cpt-empty-state"><x-heroicon-o-document-magnifying-glass /><strong>Aucun mouvement pour ce compte</strong><span>Essayez un autre compte ou vérifiez les écritures comptabilisées de la période.</span></div>
                        @endforelse
                    </div>
                @endif
            </div>

        @else
            <div class="rf-card">
                <div class="cpt-section-heading"><div><span class="cpt-overline">Rentabilité par axe</span><h2>Résultat analytique</h2></div></div>
                <p class="cpt-section-caption">Produits de classe 7 moins charges de classe 6, pour les écritures rattachées à une section analytique.</p>
                <div class="cpt-report-cards cpt-analytic-grid cpt-scroll">
                    @forelse ($d['analytique'] as $l)
                        <article class="cpt-report-card cpt-analytic-card">
                            <div class="cpt-analytic-heading">
                                <span class="cpt-account-number">{{ $l['code'] }}</span>
                                <span class="cpt-type-tag">{{ ['activite' => 'Activité', 'vehicule' => 'Véhicule', 'gare' => 'Gare', 'autre' => 'Autre'][$l['type']] ?? $l['type'] }}</span>
                            </div>
                            <strong class="cpt-report-card-title">{{ $l['libelle'] }}</strong>
                            <div class="cpt-metric-grid">
                                <span><small>Produits</small><strong>{{ $fmt($l['produits']) }}</strong></span>
                                <span><small>Charges</small><strong>{{ $fmt($l['charges']) }}</strong></span>
                                <span class="{{ $l['resultat'] < 0 ? 'is-negative' : 'is-positive' }}"><small>Résultat</small><strong>{{ $fmt($l['resultat']) }} <small>FCFA</small></strong></span>
                            </div>
                        </article>
                    @empty
                        <div class="cpt-empty-state"><x-heroicon-o-chart-pie /><strong>Aucune section analytique alimentée</strong><span>Les résultats apparaîtront lorsque des écritures comptabilisées seront rattachées à des sections.</span></div>
                    @endforelse
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>