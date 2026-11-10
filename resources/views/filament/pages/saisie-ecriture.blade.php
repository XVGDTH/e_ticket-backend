<x-filament-panels::page>
    @include('filament.partials.finance-styles')
    @include('filament.partials.compta-styles')

    @php
        $fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
        $verrou = $this->verrou();
        $debit = (int) collect($lignes)->sum(fn ($l) => (int) ($l['debit'] ?: 0));
        $credit = (int) collect($lignes)->sum(fn ($l) => (int) ($l['credit'] ?: 0));
        $equilibre = $debit > 0 && $debit === $credit;
        $statut = $meta['statut'] ?? null;
    @endphp

    <div class="rf sg">
        {{-- Barre du haut --}}
        <div class="sg-top">
            <a href="{{ \App\Filament\Resources\Ecritures\EcritureResource::getUrl('index') }}" class="sg-back">← Retour au journal</a>
            <div class="sg-top-info">
                @if ($ecritureId)
                    <span class="sg-mono sg-strong">{{ $meta['numero'] }}</span>
                    <span class="sg-chip {{ $statut === 'comptabilise' ? 'ok' : 'wait' }}">{{ $statut === 'comptabilise' ? 'Comptabilisée' : 'Brouillon' }}</span>
                @else
                    <span class="sg-chip wait">Nouvelle écriture</span>
                @endif
            </div>
        </div>

        @if ($verrou)
            <div class="sg-note" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                <span>Cette écriture est enregistrée et ne peut plus être modifiée. Pour corriger une erreur, passez une écriture inverse.</span>
                @if ($this->peutContrepasser())
                    <x-filament::button color="warning" icon="heroicon-o-arrow-uturn-left" wire:click="contrepasser" wire:confirm="Passer l'écriture inverse à la date du jour ?">
                        Contre-passer
                    </x-filament::button>
                @endif
            </div>
        @endif

        {{-- En-tête --}}
        <section class="rf-card">
            <div class="sg-form">
                @if (auth()->user()->isSuperAdmin())
                    <label class="sg-field"><span>Compagnie</span>
                        <select class="sg-in" wire:model.live="entete.compagnie_id" @disabled($verrou || $ecritureId)>
                            @foreach ($compagnies as $id => $nom)<option value="{{ $id }}">{{ $nom }}</option>@endforeach
                        </select>
                    </label>
                @endif
                <label class="sg-field"><span>Code journal</span>
                    <select class="sg-in" wire:model.live="entete.journal_id" @disabled($verrou)>
                        <option value="">Choisir…</option>
                        @foreach ($journaux as $id => $lib)<option value="{{ $id }}">{{ $lib }}</option>@endforeach
                    </select>
                </label>
                <label class="sg-field"><span>Date</span>
                    <input type="date" class="sg-in" wire:model.blur="entete.date_ecriture" @disabled($verrou)>
                </label>
                <label class="sg-field"><span>N° de pièce (facture, reçu)</span>
                    <input type="text" class="sg-in" wire:model.blur="entete.reference" maxlength="60" @disabled($verrou)>
                </label>
                <label class="sg-field sg-wide"><span>Libellé de l'écriture</span>
                    <input type="text" class="sg-in" wire:model.blur="entete.libelle" maxlength="160" @disabled($verrou)>
                </label>
                <label class="sg-field sg-check">
                    <input type="checkbox" wire:model.live="entete.est_facture" @disabled($verrou)>
                    <span>C'est une facture</span>
                </label>
            </div>

            @if ($entete['est_facture'])
                <div class="sg-form sg-facture">
                    <label class="sg-field"><span>Client / Fournisseur</span>
                        <input type="text" class="sg-in" wire:model.blur="entete.tiers" maxlength="120" @disabled($verrou)>
                    </label>
                    <label class="sg-field"><span>Échéance de règlement</span>
                        <input type="date" class="sg-in" wire:model.blur="entete.date_echeance" @disabled($verrou)>
                    </label>
                    <div class="sg-field"><span>N° de facture</span>
                        <strong class="sg-mono">{{ $meta['numero_facture'] ?? 'Attribué à l\'enregistrement' }}</strong>
                    </div>
                    <div class="sg-field"><span>Code plateforme kmaa</span>
                        @if (! empty($meta['code_kmaa']))<strong class="sg-kmaa">{{ $meta['code_kmaa'] }}</strong>@else<strong>Attribué à l'enregistrement</strong>@endif
                    </div>
                </div>
            @endif
        </section>

        {{-- Grille de saisie --}}
        <section class="rf-card">
            <div class="sg-wrap">
                <table class="sg-table sg-saisie" style="min-width:900px">
                    <thead>
                        <tr>
                            <th style="width:46px">N°</th>
                            <th style="width:120px">Compte</th>
                            <th>Intitulé du compte</th>
                            <th>Libellé de la ligne</th>
                            <th class="n" style="width:130px">Débit</th>
                            <th class="n" style="width:130px">Crédit</th>
                            <th style="width:190px">Section analytique</th>
                            <th style="width:90px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lignes as $i => $l)
                            @php $montant = max((int) ($l['debit'] ?: 0), (int) ($l['credit'] ?: 0)); @endphp
                            <tr wire:key="ligne-{{ $i }}-{{ $l['id'] ?? 'n' }}">
                                <td><span class="sg-num">{{ $i + 1 }}</span></td>
                                <td><input type="text" list="liste-comptes" class="sg-in sg-mono" placeholder="N° compte" wire:model.blur="lignes.{{ $i }}.compte" @disabled($verrou)></td>
                                <td class="{{ $l['intitule'] === 'Compte inconnu' ? 'sg-bad' : '' }}">{{ $l['intitule'] ?: '—' }}</td>
                                <td><input type="text" class="sg-in" wire:model.blur="lignes.{{ $i }}.libelle" maxlength="160" @disabled($verrou)></td>
                                <td><input type="number" min="0" class="sg-in n" wire:model.blur="lignes.{{ $i }}.debit" @disabled($verrou)></td>
                                <td><input type="number" min="0" class="sg-in n" wire:model.blur="lignes.{{ $i }}.credit" @disabled($verrou)></td>
                                <td>
                                    <select class="sg-in" wire:model.live="lignes.{{ $i }}.section_id" @disabled($verrou)>
                                        <option value="">— Aucune —</option>
                                        @foreach ($sections as $id => $lib)<option value="{{ $id }}">{{ $lib }}</option>@endforeach
                                    </select>
                                    @if (count($l['imputations']) > 1)
                                        <small class="sg-small">Ventilée sur {{ count($l['imputations']) }} sections</small>
                                    @endif
                                </td>
                                <td class="sg-row-actions">
                                    @if ($montant > 0 && filled($l['section_id']))
                                        <button type="button" class="sg-btn-mini" wire:click="ouvrirModal({{ $i }})" title="Voir l'imputation">Imputation</button>
                                    @endif
                                    @unless ($verrou)
                                        <button type="button" class="sg-btn-x" wire:click="retirerLigne({{ $i }})" title="Supprimer la ligne">✕</button>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4">
                                @unless ($verrou)
                                    <button type="button" class="sg-btn-mini" wire:click="ajouterLigne">+ Ajouter une ligne</button>
                                @endunless
                            </td>
                            <td class="n">{{ $fmt($debit) }}</td>
                            <td class="n">{{ $fmt($credit) }}</td>
                            <td colspan="2">
                                <span class="sg-chip {{ $equilibre ? 'ok' : ($debit + $credit === 0 ? 'wait' : 'bad') }}">
                                    {{ $equilibre ? '✔ Équilibrée' : ($debit + $credit === 0 ? 'Saisissez les montants' : '✖ Écart de '.$fmt(abs($debit - $credit)).' FCFA') }}
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <datalist id="liste-comptes">
                @foreach ($comptes as $numero => $c)
                    <option value="{{ $numero }}">{{ $c['intitule'] }}</option>
                @endforeach
            </datalist>

            <div class="sg-footer">
                <span class="sg-small">Tapez le numéro du compte (ex. 401). Vérifiez vos montants : une fois enregistrée, l'écriture est comptabilisée.</span>
                @unless ($verrou)
                    <div class="sg-actions">
                        <x-filament::button color="gray" wire:click="enregistrer(true)" wire:loading.attr="disabled"
                            wire:confirm="Enregistrer cette écriture et en saisir une nouvelle ? Une fois enregistrée, elle ne pourra plus être modifiée.">
                            Enregistrer et nouvelle écriture
                        </x-filament::button>
                        <x-filament::button icon="heroicon-o-check" wire:click="enregistrer" wire:loading.attr="disabled"
                            wire:confirm="Enregistrer cette écriture ? Une fois enregistrée, elle ne pourra plus être modifiée.">
                            Enregistrer
                        </x-filament::button>
                    </div>
                @endunless
            </div>
        </section>

        {{-- Fenêtre d'imputation analytique --}}
        @if ($modal !== null && isset($lignes[$modal]))
            @php
                $L = $lignes[$modal];
                $montantL = max((int) ($L['debit'] ?: 0), (int) ($L['credit'] ?: 0));
                $somme = (int) collect($ventilation)->sum(fn ($v) => (int) ($v['montant'] ?: 0));
                $reste = $montantL - $somme;
            @endphp
            <div class="sg-overlay" wire:key="modal-{{ $modal }}">
                <div class="sg-modal" role="dialog" aria-modal="true">
                    <div class="sg-modal-head">
                        <div>
                            <div class="cpt-overline">Imputation analytique · ligne {{ $modal + 1 }}</div>
                            <h2><span class="sg-mono">{{ $L['compte'] }}</span> · {{ $L['intitule'] }}</h2>
                            <div class="sg-small">{{ $L['libelle'] ?: '—' }}</div>
                        </div>
                        <div class="sg-modal-amount">
                            <small>{{ (int) ($L['debit'] ?: 0) > 0 ? 'Débit' : 'Crédit' }}</small>
                            <strong>{{ $fmt($montantL) }} FCFA</strong>
                        </div>
                    </div>

                    <div class="sg-modal-body">
                        <p class="sg-small">Répartissez le montant de la ligne entre les sections. Le total imputé doit être égal à {{ $fmt($montantL) }} FCFA.</p>

                        @foreach ($ventilation as $k => $v)
                            <div class="sg-imp-row" wire:key="imp-{{ $modal }}-{{ $k }}">
                                <select class="sg-in" wire:model.live="ventilation.{{ $k }}.section_id" @disabled($verrou)>
                                    <option value="">Section…</option>
                                    @foreach ($sections as $id => $lib)<option value="{{ $id }}">{{ $lib }}</option>@endforeach
                                </select>
                                <input type="number" min="1" class="sg-in n" wire:model.live.debounce.400ms="ventilation.{{ $k }}.montant" @disabled($verrou)>
                                <span class="sg-small">FCFA</span>
                                @unless ($verrou)
                                    <button type="button" class="sg-btn-x" wire:click="retirerVentilation({{ $k }})">✕</button>
                                @endunless
                            </div>
                        @endforeach

                        <div class="sg-imp-total">
                            @unless ($verrou)
                                <button type="button" class="sg-btn-mini" wire:click="ajouterVentilation">+ Ajouter une section</button>
                            @endunless
                            <span class="sg-chip {{ $reste === 0 ? 'ok' : 'bad' }}">
                                Imputé : {{ $fmt($somme) }} / {{ $fmt($montantL) }}
                                {{ $reste === 0 ? '✔' : '· reste '.$fmt($reste) }}
                            </span>
                        </div>
                    </div>

                    <div class="sg-modal-foot">
                        <x-filament::button color="gray" wire:click="fermerModal">Fermer</x-filament::button>
                        @unless ($verrou)
                            <x-filament::button wire:click="appliquerVentilation">Appliquer l'imputation</x-filament::button>
                        @endunless
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>