<x-filament-panels::page>
    <div class="gx">
        @include('filament.partials.gx-style')

        @php
            $f = fn ($n) => number_format((int) $n, 0, ',', ' ');
            $s = fn ($n) => ((int) $n < 0 ? '−' : ($n > 0 ? '+' : '')).number_format(abs((int) $n), 0, ',', ' ');
            $p = fn ($n) => $n === null ? '—' : number_format((float) $n, 1, ',', ' ').' %';
            $vert = '#16a34a';
            $rouge = '#dc2626';
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
                    <x-filament::input.select wire:model.live="mois">
                        @foreach ($mois_noms as $n => $nom)
                            <option value="{{ $n }}">{{ ucfirst($nom) }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            <div class="gx-filtre">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="annee">
                        @foreach ($annees as $y)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>

        @if ($this->toutesCompagnies())
            @if ($rapports->isEmpty())
                <div class="gx-vide">Aucune compagnie à analyser pour votre compte.</div>
            @else
                <div class="gx-titre">Reporting par compagnie · {{ ucfirst($mois_noms[(int) $this->mois]) }} {{ $this->annee }}</div>
                <div class="gx-grille">
                    @foreach ($rapports as $compagnieId => $donnees)
                        @php $total = $donnees['rapport']['reel']['total']; @endphp
                        <article class="gx-carte" style="--c: {{ $total['resultat'] >= 0 ? $vert : $rouge }}" wire:key="reporting-compagnie-{{ $compagnieId }}">
                            <div class="gx-n">{{ $compagnieNoms[$compagnieId] ?? 'Compagnie' }}</div>
                            <div class="gx-res">{{ $f($total['resultat']) }} FCFA</div>
                            <div class="gx-s">
                                Résultat · {{ $f($total['recettes']) }} FCFA de recettes · {{ $total['voyages'] }} départ(s)
                                @if ((int) $total['voyages'] === 0)
                                    <br>Aucun voyage de car sur ce mois.
                                @endif
                                @if ($donnees['cloture'])
                                    <br>Mois clôturé le {{ $donnees['cloture']->cloture_le->format('d/m/Y') }}
                                @else
                                    <br>Calcul en direct · non clôturé
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="gx-note">En vue globale, les rapports restent séparés par compagnie. Sélectionnez une compagnie pour clôturer le mois ou exporter ses chiffres.</div>
            @endif
        @elseif (! $r)
            <div class="gx-vide">Aucune compagnie à analyser pour votre compte.</div>
        @else
            @php
                $t = $r['reel']['total'];
                $e = $r['ecarts'];
            @endphp

            {{-- État du mois --}}
            @if ($cloture)
                <div class="gx-alerte" style="--c: #0ea5e9">
                    <b>Mois clôturé</b> le {{ $cloture->cloture_le->format('d/m/Y à H:i') }}
                    @if ($cloture->clotureur) par {{ $cloture->clotureur->getFilamentName() }} @endif
                    : chiffres arrêtés et commentaires figés. Le tableau de bord continue de s’afficher en direct.
                </div>
            @elseif ($bloquants)
                <div>
                    <div class="gx-titre">Avant de clôturer ce mois</div>
                    <div class="gx-alertes">
                        @foreach ($bloquants as $b)
                            <div class="gx-alerte" style="--c: #dc2626">{{ $b }}</div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="gx-alerte" style="--c: #16a34a"><b>Prêt à clôturer.</b> Tous les voyages sont clôturés et tous les écarts sont commentés.</div>
            @endif

            @if ((int) $t['voyages'] === 0)
                <div class="gx-vide">Aucun voyage de car sur ce mois.</div>
            @else
                <div class="gx-kpis">
                    <div class="gx-kpi gx-bleu"><div class="gx-kpi-t">Recettes</div><div class="gx-kpi-v">{{ $f($t['recettes']) }} FCFA</div><div class="gx-kpi-s">{{ $t['voyages'] }} départ(s) · {{ $f($t['pax']) }} voyageur(s)</div></div>
                    <div class="gx-kpi gx-ambre"><div class="gx-kpi-t">Coûts totaux</div><div class="gx-kpi-v">{{ $f($t['couts_total']) }} FCFA</div><div class="gx-kpi-s">{{ $t['cout_km'] !== null ? $f($t['cout_km']).' FCFA par km' : '—' }}</div></div>
                    <div class="gx-kpi {{ $t['resultat'] >= 0 ? 'gx-vert' : 'gx-rouge' }}"><div class="gx-kpi-t">Résultat</div><div class="gx-kpi-v">{{ $f($t['resultat']) }} FCFA</div><div class="gx-kpi-s">Marge {{ $p($t['marge_pct']) }}</div></div>
                    @if ($e)
                        <div class="gx-kpi {{ $e['total']['ecart']['resultat'] >= 0 ? 'gx-vert' : 'gx-rouge' }}"><div class="gx-kpi-t">Écart avec le budget</div><div class="gx-kpi-v">{{ $s($e['total']['ecart']['resultat']) }} FCFA</div><div class="gx-kpi-s">Budget version {{ $r['budget']['version'] }}</div></div>
                    @else
                        <div class="gx-kpi gx-gris"><div class="gx-kpi-t">Remplissage pondéré</div><div class="gx-kpi-v">{{ $p($t['remplissage']) }}</div><div class="gx-kpi-s">Aucun budget validé pour comparer</div></div>
                    @endif
                </div>

                @php
                    $base = max(1, abs($t['recettes']), abs($t['marge_variable']), abs($t['marge_directe']), abs($t['contribution']), abs($t['resultat']));
                    $etapes = [
                        ['Recettes', $t['recettes'], null],
                        ['Marge sur coûts variables', $t['marge_variable'], 'Énergie, frais de route, commissions : −'.$f($t['couts_variables'])],
                        ['Marge sur coûts directs', $t['marge_directe'], 'Équipage, maintenance, pneus, coûts fixes du car, incidents : −'.$f($t['couts_directs'])],
                        ['Contribution après coûts de gare', $t['contribution'], 'Gares et agences : −'.$f($t['gare'])],
                        ['Résultat après frais de siège', $t['resultat'], 'Siège et atelier : −'.$f($t['siege'])],
                    ];
                @endphp
                <div>
                    <div class="gx-titre">Cascade de rentabilité du mois</div>
                    <div class="gx-pal">
                        @foreach ($etapes as $et)
                            @php $c = $loop->first ? '#0ea5e9' : ($et[1] >= 0 ? $vert : $rouge); @endphp
                            @if ($et[2]) <div class="gx-moins">{{ $et[2] }}</div> @endif
                            <div class="gx-pal-l"><span>{{ $et[0] }}</span><b style="color: {{ $c }}">{{ $f($et[1]) }} FCFA</b></div>
                            <div class="gx-bar"><i style="width: {{ round(min(100, abs($et[1]) / $base * 100), 1) }}%; background: {{ $c }}"></i></div>
                        @endforeach
                    </div>
                </div>

                <div>
                    <div class="gx-titre">Lignes{{ $e ? ' : réel contre budget' : '' }}</div>
                    <div class="gx-grille">
                        @if ($e)
                            @foreach ($e['lignes'] as $l)
                                @php $c = $l['reel']['resultat'] >= 0 ? $vert : $rouge; @endphp
                                <div class="gx-carte" style="--c: {{ $c }}" wire:key="rl-{{ $l['cle'] }}">
                                    <div class="gx-h"><span class="gx-n">{{ $l['libelle'] }}</span>@if ($l['requis'])<span class="gx-pastille" style="--c: {{ $l['commentaire'] ? '#6366f1' : '#dc2626' }}">{{ $l['commentaire'] ? 'Commenté' : 'Commentaire requis' }}</span>@endif</div>
                                    <div class="gx-res">{{ $f($l['reel']['resultat']) }} FCFA <small>budget {{ $f($l['budget']['resultat']) }}</small></div>
                                    <div class="gx-mini">
                                        <div>{{ $f($l['reel']['recettes'] + $l['reel']['colis']) }}<small>Recettes</small></div>
                                        <div>{{ $f($l['reel']['couts']) }}<small>Coûts</small></div>
                                        <div style="color: {{ $l['ecart']['resultat'] >= 0 ? $vert : $rouge }}">{{ $s($l['ecart']['resultat']) }}<small style="color:inherit; opacity:.6">Écart</small></div>
                                    </div>
                                    @if ($l['commentaire']) <div class="gx-com">{{ $l['commentaire'] }}</div> @endif
                                </div>
                            @endforeach
                        @else
                            @foreach ($r['reel']['par_ligne'] as $i => $l)
                                <div class="gx-carte" style="--c: {{ $l['resultat'] >= 0 ? $vert : $rouge }}" wire:key="rl-{{ $i }}">
                                    <div class="gx-n">{{ $l['libelle'] }}</div>
                                    <div class="gx-res">{{ $f($l['resultat']) }} FCFA <small>{{ $p($l['marge_pct']) }}</small></div>
                                    <div class="gx-mini"><div>{{ $f($l['recettes']) }}<small>Recettes</small></div><div>{{ $f($l['couts_total']) }}<small>Coûts</small></div><div>{{ $l['voyages'] }}<small>Départs</small></div></div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>

                <div>
                    <div class="gx-titre">Cars</div>
                    <div class="gx-grille">
                        @foreach ($r['reel']['par_car'] as $i => $c)
                            <div class="gx-carte" style="--c: {{ $c['resultat'] >= 0 ? $vert : $rouge }}" wire:key="rc-{{ $i }}">
                                <div class="gx-n">{{ $c['libelle'] }}</div>
                                <div class="gx-res">{{ $f($c['resultat']) }} FCFA <small>{{ $p($c['marge_pct']) }}</small></div>
                                <div class="gx-mini"><div>{{ $f($c['recettes']) }}<small>Recettes</small></div><div>{{ $f($c['couts_total']) }}<small>Coûts</small></div><div>{{ $p($c['remplissage']) }}<small>Remplissage</small></div></div>
                                @if (! empty($c['immobilisation'])) <div class="gx-s">Jours sans voyage : −{{ $f($c['immobilisation']) }} FCFA</div> @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>