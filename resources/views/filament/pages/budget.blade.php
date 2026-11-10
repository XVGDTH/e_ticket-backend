<x-filament-panels::page>
    <div class="gx">
        @include('filament.partials.gx-style')

        @php
            $f = fn ($n) => number_format((int) $n, 0, ',', ' ');
            $s = fn ($n) => ((int) $n < 0 ? '−' : '').number_format(abs((int) $n), 0, ',', ' ');
        @endphp

        <div class="gx-filtres">
            @if ($compagnies->count() > 1)
                <div class="gx-filtre">
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="compagnieId">
                            @foreach ($compagnies as $id => $nom)
                                <option value="{{ $id }}">{{ $nom }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            @endif
            <div class="gx-filtre">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="annee">
                        @foreach ($annees as $y)
                            <option value="{{ $y }}">Année {{ $y }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>

        @if ($budgets->isEmpty())
            <div class="gx-vide">Aucun budget pour {{ $this->annee }}. Utilisez « Nouveau budget » : il est calculé à partir de vos lignes, horaires, tarifs et distances réels.</div>
        @else
            <div>
                <div class="gx-titre">Versions du budget {{ $this->annee }}</div>
                <div class="gx-grille">
                    @foreach ($budgets as $b)
                        <div class="gx-carte" style="--c: {{ $b->estValide() ? '#16a34a' : '#f59e0b' }}" wire:key="budget-{{ $b->id }}">
                            <div class="gx-h">
                                <span class="gx-n">{{ $b->libelle }}</span>
                                <span class="gx-pastille">V{{ $b->version }} · {{ $b->estValide() ? 'Validé' : 'Brouillon' }}</span>
                            </div>
                            <div class="gx-s">
                                @if ($this->toutesCompagnies())
                                    <strong>{{ $compagnieNoms[$b->compagnie_id] ?? 'Compagnie inconnue' }}</strong><br>
                                @endif
                                Créé le {{ $b->created_at->format('d/m/Y') }}
                                @if ($b->valide_le) · validé le {{ $b->valide_le->format('d/m/Y') }} @endif
                                <br>Remplissage cible {{ rtrim(rtrim(number_format($b->remplissage_cible_pct, 1, ',', ' '), '0'), ',') }} %
                                · carburant {{ $b->norme_l100 }} L/100 km à {{ $f($b->prix_litre) }} FCFA
                                <br>Maintenance {{ $f($b->cout_km_maintenance) }}/km · pneus {{ $f($b->cout_km_pneus) }}/km · route {{ $f($b->frais_route_depart) }}/départ
                                <br>Équipage {{ $f($b->cout_horaire_equipage) }}/h · coûts fixes {{ $f($b->fixes_car_mensuel) }}/mois
                                @if ($b->note) <br>{{ $b->note }} @endif
                            </div>
                            <div class="gx-btns">
                                @if ($this->toutesCompagnies())
                                    <button type="button" class="gx-btn" wire:click="choisirBudget({{ $b->id }})">Voir pour cette compagnie</button>
                                @else
                                    <button type="button" class="gx-btn" wire:click="choisirBudget({{ $b->id }})">{{ $choisi && $choisi->id === $b->id ? 'Affiché' : 'Voir' }}</button>
                                    @unless ($b->estValide())
                                        <button type="button" class="gx-btn gx-btn-v" wire:click="mountAction('valider', { id: {{ $b->id }} })">Valider</button>
                                        <button type="button" class="gx-btn gx-btn-r" wire:click="mountAction('supprimer', { id: {{ $b->id }} })">Supprimer</button>
                                    @endunless
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            @if ($choisi && $total)
                <div class="gx-kpis">
                    <div class="gx-kpi gx-bleu"><div class="gx-kpi-t">Recettes budgétées</div><div class="gx-kpi-v">{{ $f($total['recettes']) }} FCFA</div><div class="gx-kpi-s">{{ $f($total['departs']) }} départ(s) sur l’année</div></div>
                    <div class="gx-kpi gx-ambre"><div class="gx-kpi-t">Coûts budgétés</div><div class="gx-kpi-v">{{ $f($total['couts']) }} FCFA</div><div class="gx-kpi-s">{{ $f($total['km']) }} km</div></div>
                    <div class="gx-kpi {{ $total['resultat'] >= 0 ? 'gx-vert' : 'gx-rouge' }}"><div class="gx-kpi-t">Résultat budgété</div><div class="gx-kpi-v">{{ $s($total['resultat']) }} FCFA</div><div class="gx-kpi-s">Version {{ $choisi->version }}</div></div>
                </div>

                <div>
                    <div class="gx-titre">Budget mensualisé · version {{ $choisi->version }}</div>
                    <div class="gx-grille">
                        @foreach ($mensuel as $m => $x)
                            <div class="gx-carte" style="--c: {{ $x['resultat'] >= 0 ? '#16a34a' : '#dc2626' }}" wire:key="mois-{{ $m }}">
                                <div class="gx-n" style="text-transform:capitalize">{{ $mois_noms[$m] }}</div>
                                <div class="gx-res">{{ $s($x['resultat']) }} FCFA</div>
                                <div class="gx-mini">
                                    <div>{{ $f($x['recettes']) }}<small>Recettes</small></div>
                                    <div>{{ $f($x['couts']) }}<small>Coûts</small></div>
                                    <div>{{ $f($x['departs']) }}<small>Départs</small></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div>
                    <div class="gx-titre">Budget de l’année par ligne</div>
                    <div class="gx-grille">
                        @foreach ($parRoute as $i => $x)
                            <div class="gx-carte" style="--c: {{ $x['resultat'] >= 0 ? '#16a34a' : '#dc2626' }}" wire:key="ligne-{{ $i }}">
                                <div class="gx-n">{{ $x['libelle'] }}</div>
                                <div class="gx-res">{{ $s($x['resultat']) }} FCFA</div>
                                <div class="gx-mini">
                                    <div>{{ $f($x['recettes']) }}<small>Recettes</small></div>
                                    <div>{{ $f($x['couts']) }}<small>Coûts</small></div>
                                    <div>{{ $f($x['km']) }}<small>km</small></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>