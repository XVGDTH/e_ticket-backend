<x-filament-panels::page>
    @include('filament.partials.finance-styles')

    @php
        $fmt = fn ($montant) => number_format((int) $montant, 0, ',', ' ');
        $statuts = [
            'brouillon' => 'À valider',
            'active' => 'En service',
            'planifie' => 'Planifiée',
            'comptabilise' => 'Comptabilisée',
        ];
    @endphp

    <div class="rf rf-accounting cpt-page">
        <section class="rf-hero cpt-hero">
            <div class="cpt-hero-head">
                <div>
                    <div class="rf-eyebrow">Cycle des actifs · Comptabilité SYSCOHADA</div>
                    <h1>Immobilisations & amortissements</h1>
                    <p>Registre des biens, paramètres par catégorie et dotations mensuelles soumises à validation distincte.</p>
                </div>
                <span class="cpt-period-badge"><span class="cpt-status-dot"></span>{{ $d['nomCompagnie'] }} · {{ $d['devise'] }}</span>
            </div>

            @if (auth()->user()->isSuperAdmin())
                <div class="cpt-hero-filters">
                    <label>
                        <span>Compagnie</span>
                        <select wire:model.live="compagnieId" class="rf-select">
                            @foreach ($d['compagnies'] as $id => $nom)
                                <option value="{{ $id }}">{{ $nom }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            @endif
        </section>

        <section class="cpt-asset-kpis" aria-label="Résumé des immobilisations">
            <article class="rf-kpi">
                <span class="cpt-kpi-icon"><x-heroicon-o-building-office-2 /></span>
                <div class="rf-kpi-l">Biens en service</div>
                <div class="rf-kpi-v">{{ number_format($d['immobilisationsActives'], 0, ',', ' ') }}</div>
                <div class="cpt-kpi-note">Registre approuvé</div>
            </article>
            <article class="rf-kpi">
                <span class="cpt-kpi-icon is-warning"><x-heroicon-o-clock /></span>
                <div class="rf-kpi-l">À valider</div>
                <div class="rf-kpi-v">{{ number_format($d['enAttenteValidation'], 0, ',', ' ') }}</div>
                <div class="cpt-kpi-note">Séparation des tâches active</div>
            </article>
            <article class="rf-kpi">
                <span class="cpt-kpi-icon"><x-heroicon-o-calendar-days /></span>
                <div class="rf-kpi-l">Dotations dues</div>
                <div class="rf-kpi-v">{{ number_format($d['dotationsAProposer'], 0, ',', ' ') }}</div>
                <div class="cpt-kpi-note">Période échue, écriture à préparer</div>
            </article>
            <article class="rf-kpi">
                <span class="cpt-kpi-icon is-warning"><x-heroicon-o-document-check /></span>
                <div class="rf-kpi-l">Brouillons à contrôler</div>
                <div class="rf-kpi-v">{{ number_format($d['ecrituresEnAttente'], 0, ',', ' ') }}</div>
                <div class="cpt-kpi-note">À comptabiliser par un autre utilisateur</div>
            </article>
        </section>

        <nav class="cpt-shortcuts" aria-label="Autres espaces comptables">
            <a class="cpt-shortcut" href="{{ route('filament.admin.resources.ecritures.index') }}">
                <span class="cpt-shortcut-icon"><x-heroicon-o-book-open /></span>
                <span><strong>Écritures comptables</strong><small>Consulter les pièces et journaux</small></span>
                <x-heroicon-o-arrow-up-right class="cpt-shortcut-arrow" />
            </a>
            <a class="cpt-shortcut" href="{{ route('filament.admin.pages.etats-comptables') }}">
                <span class="cpt-shortcut-icon"><x-heroicon-o-chart-bar-square /></span>
                <span><strong>États financiers</strong><small>Balance, bilan et résultat</small></span>
                <x-heroicon-o-arrow-up-right class="cpt-shortcut-arrow" />
            </a>
            <div class="cpt-shortcut cpt-shortcut-current">
                <span class="cpt-shortcut-icon"><x-heroicon-o-cube /></span>
                <span><strong>Immobilisations</strong><small>Registre et amortissements</small></span>
                <span class="cpt-current-label">Vous êtes ici</span>
            </div>
        </nav>

        <section class="rf-card">
            <div class="cpt-section-heading">
                <div>
                    <span class="cpt-overline">Paramètres validés par compagnie</span>
                    <h2>Catégories d’immobilisations</h2>
                </div>
                <span class="cpt-section-caption">Méthode linéaire · durées et comptes configurables</span>
            </div>
            @if ($d['categories']->isEmpty())
                <div class="cpt-empty-state">
                    <x-heroicon-o-squares-plus />
                    <strong>Aucune catégorie configurée</strong>
                    <span>Créez une catégorie en choisissant la durée, la convention de première période et les comptes SYSCOHADA correspondants.</span>
                </div>
            @else
                <div class="cpt-report-cards cpt-category-grid">
                    @foreach ($d['categories'] as $categorie)
                        <article class="cpt-report-card cpt-category-card">
                            <div class="cpt-account-heading">
                                <span class="cpt-account-number">{{ $categorie->code }}</span>
                                <span class="cpt-type-tag {{ $categorie->actif ? 'is-ready' : 'is-pending' }}">{{ $categorie->actif ? 'Active' : 'À configurer' }}</span>
                            </div>
                            <strong class="cpt-report-card-title">{{ $categorie->libelle }}</strong>
                            <div class="cpt-category-details">
                                <span><small>Méthode</small><strong>Linéaire</strong></span>
                                <span><small>Durée</small><strong>{{ $categorie->duree_utilite_mois ? $categorie->duree_utilite_mois.' mois' : 'À renseigner' }}</strong></span>
                                <span><small>Première période</small><strong>{{ $d['reglesPremierePeriode'][$categorie->premiere_periode] ?? 'À renseigner' }}</strong></span>
                            </div>
                            <small class="cpt-category-count">{{ number_format($categorie->immobilisations_count, 0, ',', ' ') }} bien(s) rattaché(s)</small>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="rf-card">
            <div class="cpt-section-heading">
                <div>
                    <span class="cpt-overline">Registre des biens</span>
                    <h2>Immobilisations de la compagnie</h2>
                </div>
                <span class="cpt-section-caption">Montants en {{ $d['devise'] }} · les acquisitions sont enregistrées séparément dans le journal</span>
            </div>
            @if ($d['immobilisations']->isEmpty())
                <div class="cpt-empty-state">
                    <x-heroicon-o-building-office-2 />
                    <strong>Le registre est vide</strong>
                    <span>Activez une catégorie paramétrée, puis enregistrez le bien. Il restera en brouillon jusqu’à sa validation.</span>
                </div>
            @else
                <div class="cpt-report-cards cpt-asset-grid cpt-scroll">
                    @foreach ($d['immobilisations'] as $immobilisation)
                        <article class="cpt-report-card cpt-asset-card">
                            <div class="cpt-account-heading">
                                <span class="cpt-account-number">{{ $immobilisation->code }}</span>
                                <span class="cpt-type-tag {{ $immobilisation->statut === 'active' ? 'is-ready' : 'is-pending' }}">
                                    {{ $statuts[$immobilisation->statut] ?? $immobilisation->statut }}
                                </span>
                            </div>
                            <strong class="cpt-report-card-title">{{ $immobilisation->designation }}</strong>
                            <span class="cpt-asset-category">{{ $immobilisation->categorie->libelle }}</span>
                            <div class="cpt-metric-grid">
                                <span><small>Coût d’acquisition</small><strong>{{ $fmt($immobilisation->cout_acquisition) }} {{ $d['devise'] }}</strong></span>
                                <span><small>Valeur résiduelle</small><strong>{{ $fmt($immobilisation->valeur_residuelle) }} {{ $d['devise'] }}</strong></span>
                                <span><small>Mise en service</small><strong>{{ $immobilisation->date_mise_en_service->format('d/m/Y') }}</strong></span>
                                <span><small>Durée d’utilité</small><strong>{{ $immobilisation->duree_utilite_mois }} mois</strong></span>
                            </div>
                            <small class="cpt-category-count">{{ $immobilisation->periodes_planifiees_count }} période(s) planifiée(s) à proposer</small>
                            @if ($immobilisation->statut === 'brouillon' && auth()->id() !== $immobilisation->cree_par)
                                <x-filament::button size="sm" color="primary" wire:click="validerImmobilisation({{ $immobilisation->id }})">
                                    Valider l’immobilisation
                                </x-filament::button>
                            @elseif ($immobilisation->statut === 'brouillon')
                                <span class="cpt-action-hint">En attente d’un validateur différent du créateur.</span>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="rf-card">
            <div class="cpt-section-heading">
                <div>
                    <span class="cpt-overline">Cycle mensuel · validation à deux personnes</span>
                    <h2>Propositions de dotation</h2>
                </div>
                <span class="cpt-section-caption">Les écritures sont générées en brouillon et ne modifient pas les états avant comptabilisation.</span>
            </div>
            @if ($d['amortissements']->isEmpty())
                <div class="cpt-empty-state">
                    <x-heroicon-o-calendar-days />
                    <strong>Aucune proposition à traiter</strong>
                    <span>Les périodes apparaîtront après validation d’une immobilisation. Seules les périodes échues peuvent être proposées.</span>
                </div>
            @else
                <div class="cpt-report-cards cpt-asset-grid cpt-scroll">
                    @foreach ($d['amortissements'] as $amortissement)
                        <article class="cpt-report-card cpt-amort-card">
                            <div class="cpt-account-heading">
                                <span class="cpt-date-tag">{{ $amortissement->periode_debut->format('d/m/Y') }} → {{ $amortissement->periode_fin->format('d/m/Y') }}</span>
                                <span class="cpt-type-tag {{ $amortissement->statut === 'brouillon' ? 'is-pending' : '' }}">
                                    {{ $statuts[$amortissement->statut] ?? $amortissement->statut }}
                                </span>
                            </div>
                            <strong class="cpt-report-card-title">{{ $amortissement->immobilisation->designation }}</strong>
                            <span class="cpt-asset-category">{{ $amortissement->immobilisation->code }} · {{ $amortissement->immobilisation->categorie->libelle }}</span>
                            <div class="cpt-amort-amount">{{ $fmt($amortissement->montant) }} <small>{{ $d['devise'] }}</small></div>
                            @if ($amortissement->statut === 'planifie' && $amortissement->periode_fin->lte(today()))
                                <x-filament::button size="sm" color="gray" wire:click="proposerEcriture({{ $amortissement->id }})">
                                    Préparer l’écriture brouillon
                                </x-filament::button>
                            @elseif ($amortissement->statut === 'planifie')
                                <span class="cpt-action-hint">Proposition disponible à la fin de la période.</span>
                            @elseif ($amortissement->statut === 'brouillon')
                                <span class="cpt-action-hint">Pièce {{ $amortissement->ecriture?->numero ?? '—' }} · préparée par un autre utilisateur à la comptabilisation.</span>
                                @if (auth()->id() !== $amortissement->cree_par)
                                    <x-filament::button size="sm" color="success" wire:click="comptabiliserAmortissement({{ $amortissement->id }})">
                                        Vérifier et comptabiliser
                                    </x-filament::button>
                                @endif
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
