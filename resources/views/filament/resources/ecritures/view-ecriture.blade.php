<x-filament-panels::page>
    @include('filament.partials.finance-styles')
    @include('filament.partials.compta-styles')

    @php
        $e = $this->getRecord()->load(['journal', 'exercice', 'lignes.compte', 'lignes.section', 'lignes.validePar', 'lignes.imputations.section']);
        $fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
        $lignes = $e->lignes;
        $total = $lignes->count();
        $ok = $lignes->whereNotNull('valide_le')->count();
        $pct = $total ? (int) round($ok * 100 / $total) : 0;
        $debit = (int) $lignes->sum('debit');
        $credit = (int) $lignes->sum('credit');
        $equilibre = $debit > 0 && $debit === $credit;
        $suivante = $lignes->first(fn ($l) => ! $l->valide_le);
        $comptabilisee = $e->statut === 'comptabilise';
        $peut = ! $comptabilisee && ($e->origine !== 'manuelle' || (int) $e->cree_par !== (int) auth()->id());
        $origines = ['manuelle' => 'Saisie manuelle', 'automatique' => 'Écriture automatique', 'contrepassation' => 'Contre-passation'];
        $statutLib = $comptabilisee ? 'Comptabilisée' : ($ok > 0 ? 'En validation' : 'À valider');
        $ventilees = $lignes->filter(fn ($l) => $l->imputations->isNotEmpty());
    @endphp

    <div class="rf rf-accounting sg">
        <section class="rf-hero cpt-hero">
            <div class="cpt-hero-head">
                <div>
                    <div class="rf-eyebrow">{{ $e->journal?->code }} · {{ $e->journal?->libelle }} · {{ $origines[$e->origine] ?? $e->origine }}</div>
                    <h1 class="sg-mono">{{ $e->numero }}</h1>
                    <p>{{ $e->libelle }}</p>
                </div>
                <span class="cpt-period-badge"><span class="cpt-status-dot {{ $comptabilisee ? '' : 'is-inactive' }}"></span>{{ $statutLib }}</span>
            </div>
            <div class="sg-meta">
                <div><small>Date de l'écriture</small><strong>{{ $e->date_ecriture->format('d/m/Y') }}</strong></div>
                <div><small>Exercice</small><strong>{{ $e->exercice?->libelle ?? '—' }}</strong></div>
                <div><small>N° de la pièce</small><strong>{{ $e->reference ?: '—' }}</strong></div>
                @if ($e->est_facture)
                    <div><small>N° de facture</small><strong class="sg-mono">{{ $e->numero_facture ?: '—' }}</strong></div>
                    <div><small>Code plateforme kmaa</small><strong>@if ($e->code_kmaa)<span class="sg-kmaa">{{ $e->code_kmaa }}</span>@else — @endif</strong></div>
                    <div><small>Client / Fournisseur</small><strong>{{ $e->tiers ?: '—' }}</strong></div>
                    <div><small>Échéance</small><strong>{{ $e->date_echeance?->format('d/m/Y') ?? '—' }}</strong></div>
                @endif
                @if ($comptabilisee)
                    <div><small>Comptabilisée le</small><strong>{{ $e->comptabilise_le?->format('d/m/Y H:i') }}</strong></div>
                @endif
            </div>
        </section>

        <section class="sg-kpis" aria-label="Contrôles de l'écriture">
            <article class="sg-kpi"><small>Total débit</small><strong>{{ $fmt($debit) }} <small>FCFA</small></strong></article>
            <article class="sg-kpi"><small>Total crédit</small><strong>{{ $fmt($credit) }} <small>FCFA</small></strong></article>
            <article class="sg-kpi {{ $equilibre ? 'is-ok' : 'is-bad' }}"><small>Équilibre</small><strong>{{ $equilibre ? '✔ Équilibrée' : '✖ Écart '.$fmt(abs($debit - $credit)) }}</strong></article>
            <article class="sg-kpi {{ $comptabilisee ? 'is-ok' : '' }}"><small>Validation</small><strong>{{ $ok }} / {{ $total }} lignes</strong></article>
        </section>

        <section class="rf-card">
            <div class="cpt-section-heading">
                <div><span class="cpt-overline">Validation ligne par ligne</span><h2>Progression : {{ $pct }} %</h2></div>
                @unless ($comptabilisee)
                    <div class="sg-actions">
                        <x-filament::button size="sm" color="primary" icon="heroicon-o-check" wire:click="validerSuivante" wire:loading.attr="disabled" :disabled="! $peut || ! $suivante">
                            Valider la ligne {{ $suivante ? ($lignes->search(fn ($l) => $l->id === $suivante->id) + 1) : '' }}
                        </x-filament::button>
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-check-badge" wire:click="validerToutes" wire:loading.attr="disabled" wire:confirm="Valider toutes les lignes restantes, une par une, dans l'ordre ?" :disabled="! $peut || ! $suivante">
                            Valider toutes les lignes restantes
                        </x-filament::button>
                    </div>
                @endunless
            </div>
            <div class="sg-prog">
                <div class="sg-bar {{ $comptabilisee ? 'is-done' : '' }}"><i style="width: {{ $pct }}%"></i></div>
                <div class="sg-steps" aria-hidden="true">
                    @foreach ($lignes as $i => $l)
                        <span class="sg-step {{ $l->valide_le ? 'is-ok' : ($suivante && $suivante->id === $l->id ? 'is-next' : '') }}">{{ $i + 1 }}</span>
                    @endforeach
                </div>
            </div>
            @if (! $comptabilisee && ! $peut)
                <div class="sg-note" style="margin-top:14px">Séparation des tâches : vous avez saisi cette écriture, une autre personne doit valider ses lignes.</div>
            @elseif (! $comptabilisee)
                <div class="sg-note" style="margin-top:14px">Les lignes se valident dans l'ordre. L'écriture est comptabilisée dès que la dernière ligne est validée ; elle n'apparaît dans les états financiers qu'à ce moment.</div>
            @endif
        </section>

        <section class="rf-card">
            <div class="cpt-section-heading">
                <div><span class="cpt-overline">Détail de la saisie</span><h2>Lignes de l'écriture</h2></div>
                <span class="cpt-section-caption">Montants en FCFA</span>
            </div>
            <div class="sg-wrap">
                <table class="sg-table">
                    <thead>
                        <tr>
                            <th>N°</th><th>Compte</th><th>Intitulé</th><th>Libellé</th>
                            <th class="n">Débit</th><th class="n">Crédit</th><th>Section</th><th>Validation</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lignes as $i => $l)
                            @php $estSuivante = $suivante && $suivante->id === $l->id; @endphp
                            <tr class="{{ $l->valide_le ? 'is-valid' : ($estSuivante ? 'is-next' : '') }}" wire:key="ligne-{{ $l->id }}">
                                <td><span class="sg-num">{{ $i + 1 }}</span></td>
                                <td class="sg-compte">{{ $l->compte?->numero }}</td>
                                <td>{{ $l->compte?->intitule }}</td>
                                <td>{{ $l->libelle ?: '—' }}</td>
                                <td class="n">{{ $l->debit ? $fmt($l->debit) : '—' }}</td>
                                <td class="n">{{ $l->credit ? $fmt($l->credit) : '—' }}</td>
                                <td>{{ $l->section ? $l->section->code.' · '.$l->section->libelle : '—' }}</td>
                                <td>
                                    @if ($l->valide_le)
                                        <span class="sg-chip ok">✔ Validée</span>
                                        <small class="sg-small">{{ $l->valide_le->format('d/m/Y H:i') }} · {{ $l->validePar?->name ?? 'Système' }}</small>
                                    @elseif ($estSuivante && $peut)
                                        <x-filament::button size="xs" color="success" wire:click="validerLigne({{ $l->id }})" wire:loading.attr="disabled">Valider cette ligne</x-filament::button>
                                    @elseif ($estSuivante)
                                        <span class="sg-chip next">À valider (autre personne)</span>
                                    @else
                                        <span class="sg-chip wait">En attente</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4">TOTAUX</td>
                            <td class="n">{{ $fmt($debit) }}</td>
                            <td class="n">{{ $fmt($credit) }}</td>
                            <td colspan="2">{{ $equilibre ? '✔ Équilibrée' : '✖ Déséquilibrée' }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <section class="rf-card">
            <div class="cpt-section-heading">
                <div><span class="cpt-overline">Comptabilité analytique</span><h2>Ventilation et montants imputés</h2></div>
                <span class="cpt-section-caption">Les lignes sans ventilation comptent à 100 % sur leur section</span>
            </div>
            @if ($ventilees->isEmpty())
                <div class="cpt-empty-state"><x-heroicon-o-chart-pie /><strong>Aucune ventilation saisie</strong><span>Les montants de cette écriture ne sont pas répartis entre plusieurs sections.</span></div>
            @else
                <div class="sg-ventil">
                    @foreach ($ventilees as $l)
                        @php $imputé = (int) $l->imputations->sum('montant'); @endphp
                        <article class="sg-ventil-card">
                            <div class="cpt-account-heading">
                                <span class="cpt-account-number">{{ $l->compte?->numero }}</span>
                                <span class="sg-chip {{ $imputé === $l->montant() ? 'ok' : 'next' }}">{{ $fmt($imputé) }} / {{ $fmt($l->montant()) }}</span>
                            </div>
                            <strong class="cpt-report-card-title">{{ $l->compte?->intitule }}</strong>
                            @foreach ($l->imputations as $im)
                                <div class="sg-ventil-row">
                                    <span>{{ $im->section?->code }} · {{ $im->section?->libelle }}</span>
                                    <strong>{{ $fmt($im->montant) }} <small class="sg-small" style="display:inline">({{ rtrim(rtrim(number_format((float) $im->pourcentage, 2, ',', ''), '0'), ',') }} %)</small></strong>
                                </div>
                            @endforeach
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        @if (! empty($e->pieces))
            <section class="rf-card">
                <div class="cpt-section-heading"><div><span class="cpt-overline">Justificatifs</span><h2>Pièces jointes</h2></div></div>
                <div class="sg-actions">
                    @foreach ($e->pieces as $i => $chemin)
                        <a class="cpt-shortcut" style="flex:0 1 auto" target="_blank" href="{{ route('compta.piece', [$e->id, $i]) }}">
                            <span class="cpt-shortcut-icon"><x-heroicon-o-paper-clip /></span>
                            <span><strong>{{ basename($chemin) }}</strong><small>Ouvrir le justificatif</small></span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
