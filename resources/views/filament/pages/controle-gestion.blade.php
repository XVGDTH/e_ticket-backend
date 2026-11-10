<x-filament-panels::page>
    <div class="cg">
        <style>
            .cg { display:flex; flex-direction:column; gap:1.1rem; width:100%; min-width:0; box-sizing:border-box; }
            .cg-filtres { display:flex; flex-wrap:wrap; align-items:center; gap:.6rem; }
            .cg-filtre { min-width:200px; }
            .cg-note { font-size:.85rem; opacity:.7; }
            .cg-vide { padding:1.5rem; text-align:center; opacity:.75; border:1px dashed rgba(128,128,128,.4); border-radius:1rem; }
            .cg-titre { font-size:.72rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; opacity:.6; margin-bottom:.5rem; }
            .cg-kpis { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:.8rem; }
            .cg-kpi { color:#fff; border-radius:1rem; padding:1rem 1.2rem; box-shadow:0 12px 28px -14px rgba(0,0,0,.55); }
            .cg-kpi-t { font-size:.72rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; opacity:.92; }
            .cg-kpi-v { font-size:1.8rem; font-weight:800; line-height:1.1; margin:.35rem 0 .15rem; }
            .cg-kpi-s { font-size:.8rem; opacity:.92; }
            .cg-bleu { background:linear-gradient(135deg, #0ea5e9, #6366f1); }
            .cg-ambre { background:linear-gradient(135deg, #f59e0b, #ea580c); }
            .cg-vert { background:linear-gradient(135deg, #22c55e, #0d9488); }
            .cg-rouge { background:linear-gradient(135deg, #f97316, #dc2626); }
            .cg-jauge-carte { display:flex; align-items:center; gap:1rem; padding:1rem 1.2rem; border-radius:1rem; border:1px solid rgba(128,128,128,.3); }
            .cg-jauge { --p:0; --c:#22c55e; position:relative; width:5rem; height:5rem; flex:none; }
            .cg-jauge i { position:absolute; inset:0; border-radius:999px; background:conic-gradient(var(--c) calc(var(--p) * 1%), rgba(128,128,128,.25) 0); -webkit-mask:radial-gradient(farthest-side, transparent calc(100% - .5rem), #000 calc(100% - .5rem + 1px)); mask:radial-gradient(farthest-side, transparent calc(100% - .5rem), #000 calc(100% - .5rem + 1px)); }
            .cg-jauge b { position:absolute; inset:0; display:grid; place-items:center; font-size:1rem; }
            .cg-jauge-t { font-size:.8rem; opacity:.75; line-height:1.4; }
            .cg-chips { display:flex; flex-wrap:wrap; gap:.5rem; }
            .cg-chip { display:flex; flex-direction:column; padding:.4rem .8rem; border-radius:.8rem; border:1px solid rgba(128,128,128,.3); font-size:.85rem; font-weight:700; }
            .cg-chip small { font-size:.68rem; font-weight:500; opacity:.65; }
            .cg-pal { display:flex; flex-direction:column; gap:.4rem; padding:1rem 1.2rem; border-radius:1rem; border:1px solid rgba(128,128,128,.3); }
            .cg-pal-l { display:flex; justify-content:space-between; gap:1rem; flex-wrap:wrap; font-size:.9rem; }
            .cg-pal-l b { font-variant-numeric:tabular-nums; }
            .cg-moins { font-size:.75rem; opacity:.6; margin-top:.2rem; }
            .cg-bar { height:.7rem; border-radius:999px; background:rgba(128,128,128,.18); overflow:hidden; }
            .cg-bar i { display:block; height:100%; border-radius:999px; }
            .cg-alertes { display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:.6rem; }
            .cg-alerte { --c:#6b7280; padding:.65rem .9rem; border-radius:.8rem; border:1px solid color-mix(in srgb, var(--c) 45%, transparent); background:color-mix(in srgb, var(--c) 10%, transparent); }
            .cg-alerte b { display:block; font-size:.88rem; }
            .cg-alerte span { font-size:.8rem; opacity:.85; }
            .cg-vues { display:flex; flex-wrap:wrap; gap:.5rem; }
            .cg-vue { --c:#6366f1; display:flex; align-items:center; gap:.5rem; padding:.45rem .9rem; cursor:pointer; color:inherit; font-size:.85rem; border-radius:999px; border:1px solid color-mix(in srgb, var(--c) 55%, transparent); background:transparent; }
            .cg-vue:hover { background:color-mix(in srgb, var(--c) 15%, transparent); }
            .cg-vue-on { background:color-mix(in srgb, var(--c) 22%, transparent); box-shadow:0 0 0 1px var(--c); font-weight:600; }
            .cg-vue-n { min-width:1.6rem; padding:.05rem .45rem; text-align:center; font-weight:800; color:#fff; border-radius:999px; background:var(--c); }
            .cg-grille { display:grid; grid-template-columns:repeat(auto-fill, minmax(290px, 1fr)); gap:.8rem; }
            .cg-carte { --c:#6b7280; display:flex; flex-direction:column; gap:.5rem; padding:.9rem 1rem; border-radius:1rem; border:1px solid rgba(128,128,128,.3); border-left:5px solid var(--c); }
            .cg-carte-h { display:flex; justify-content:space-between; align-items:flex-start; gap:.5rem; }
            .cg-carte-n { font-weight:700; line-height:1.25; }
            .cg-pastille { flex:none; padding:.1rem .55rem; font-size:.68rem; font-weight:700; color:#fff; border-radius:999px; background:var(--c); }
            .cg-res { font-size:1.5rem; font-weight:800; color:var(--c); }
            .cg-res small { font-size:.75rem; font-weight:600; opacity:.8; }
            .cg-mini { display:grid; grid-template-columns:repeat(3, 1fr); gap:.4rem; font-size:.8rem; font-weight:600; }
            .cg-mini div { display:flex; flex-direction:column; }
            .cg-mini small { font-weight:400; opacity:.6; }
            .cg-prog { height:.45rem; border-radius:999px; background:rgba(128,128,128,.2); overflow:hidden; }
            .cg-prog i { display:block; height:100%; background:#0ea5e9; }
            .cg-immo { font-size:.78rem; opacity:.8; }
            .cg-btn { align-self:flex-start; padding:.2rem .7rem; font-size:.78rem; font-weight:600; cursor:pointer; color:inherit; background:transparent; border-radius:999px; border:1px solid rgba(128,128,128,.4); }
            .cg-btn:hover { border-color:#6366f1; }
            .cg-det { display:flex; flex-direction:column; gap:.15rem; padding-top:.5rem; font-size:.8rem; border-top:1px dashed rgba(128,128,128,.35); }
            .cg-det div { display:flex; justify-content:space-between; gap:.5rem; }
            .cg-det .g { margin-top:.35rem; font-size:.68rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; opacity:.6; }
            [x-cloak] { display:none !important; }
        </style>

        @php
            $a = $d['a'];
            $f = fn ($n) => number_format((int) $n, 0, ',', ' ');
            $s = fn ($n) => ((int) $n < 0 ? '−' : '').number_format(abs((int) $n), 0, ',', ' ');
            $p = fn ($n) => $n === null ? '—' : number_format((float) $n, 1, ',', ' ').' %';
        @endphp

        {{-- Filtres --}}
        <div class="cg-filtres">
            @if ($d['compagnies']->count() > 1)
                <div class="cg-filtre">
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="compagnieId">
                            @foreach ($d['compagnies'] as $id => $nom)
                                <option value="{{ $id }}">{{ $nom }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            @endif

            <div class="cg-filtre">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="periode">
                        @foreach ($d['periodes'] as $cle => $nom)
                            <option value="{{ $cle }}">{{ $nom }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            @if ($this->periode === 'perso')
                <div class="cg-filtre">
                    <x-filament::input.wrapper>
                        <x-filament::input type="date" wire:model.live="du" />
                    </x-filament::input.wrapper>
                </div>
                <div class="cg-filtre">
                    <x-filament::input.wrapper>
                        <x-filament::input type="date" wire:model.live="au" />
                    </x-filament::input.wrapper>
                </div>
            @endif
        </div>

        @if ($d['parCompagnie']->isNotEmpty())
            <div class="cg-note">Vue globale en lecture seule · les résultats sont présentés séparément pour chaque compagnie.</div>
            <div class="cg-kpis">
                @foreach ($d['parCompagnie'] as $ligne)
                    @php $total = $ligne['analyse']['total']; @endphp
                    <article class="cg-kpi {{ $total['resultat'] >= 0 ? 'cg-vert' : 'cg-rouge' }}" wire:key="cg-compagnie-{{ $ligne['id'] }}">
                        <div class="cg-kpi-t">{{ $ligne['nom'] }}</div>
                        <div class="cg-kpi-v">{{ $s($total['resultat']) }} FCFA</div>
                        <div class="cg-kpi-s">{{ $f($total['recettes']) }} FCFA · {{ $total['voyages'] }} départ(s) · {{ $f($total['pax']) }} voyageur(s)</div>
                    </article>
                @endforeach
            </div>
        @elseif (! $a)
            <div class="cg-vide">Aucune compagnie à analyser pour votre compte.</div>
        @else
            @php $t = $a['total']; @endphp

            <div class="cg-note">
                {{ $d['compagnie'] }} · du {{ $a['debut']->format('d/m/Y') }} au {{ $a['fin']->format('d/m/Y') }}
                · coûts standards tant que les voyages ne sont pas clôturés
            </div>

            @if ((int) $t['voyages'] === 0)
                <div class="cg-vide">Aucun voyage de car sur cette période. Programmez puis clôturez des voyages (Flotte, Voyages des cars) pour voir la rentabilité.</div>
            @else
                {{-- Cartes principales --}}
                @php
                    $rem = $t['remplissage'];
                    $couleurJauge = $rem === null ? '#6b7280' : ($rem < 50 ? '#ef4444' : ($rem < 70 ? '#f59e0b' : '#22c55e'));
                @endphp
                <div class="cg-kpis">
                    <div class="cg-kpi cg-bleu">
                        <div class="cg-kpi-t">Recettes</div>
                        <div class="cg-kpi-v">{{ $f($t['recettes']) }} FCFA</div>
                        <div class="cg-kpi-s">{{ $t['voyages'] }} départ(s) · {{ $f($t['pax']) }} voyageur(s)</div>
                    </div>
                    <div class="cg-kpi cg-ambre">
                        <div class="cg-kpi-t">Coûts totaux</div>
                        <div class="cg-kpi-v">{{ $f($t['couts_total']) }} FCFA</div>
                        <div class="cg-kpi-s">{{ $t['cout_km'] !== null ? $f($t['cout_km']).' FCFA par km' : 'Distance non renseignée' }}</div>
                    </div>
                    <div class="cg-kpi {{ $t['resultat'] >= 0 ? 'cg-vert' : 'cg-rouge' }}">
                        <div class="cg-kpi-t">Résultat</div>
                        <div class="cg-kpi-v">{{ $s($t['resultat']) }} FCFA</div>
                        <div class="cg-kpi-s">Marge : {{ $p($t['marge_pct']) }}</div>
                    </div>
                    <div class="cg-jauge-carte">
                        <div class="cg-jauge" style="--p: {{ min(100, (float) ($rem ?? 0)) }}; --c: {{ $couleurJauge }}">
                            <i></i><b>{{ $rem === null ? '—' : round($rem).' %' }}</b>
                        </div>
                        <div class="cg-jauge-t"><strong>Remplissage pondéré</strong><br>par la distance (passagers-km ÷ sièges-km)</div>
                    </div>
                </div>

                <div class="cg-chips">
                    <div class="cg-chip">{{ $t['recette_siege_km'] !== null ? number_format($t['recette_siege_km'], 2, ',', ' ') : '—' }} FCFA<small>Recette par siège-km</small></div>
                    <div class="cg-chip">{{ $t['cout_siege_km'] !== null ? number_format($t['cout_siege_km'], 2, ',', ' ') : '—' }} FCFA<small>Coût par siège-km</small></div>
                    <div class="cg-chip">{{ $f($t['pax_km']) }}<small>Passagers-km</small></div>
                    <div class="cg-chip">{{ $f($t['sieges_km']) }}<small>Sièges-km offerts</small></div>
                    <div class="cg-chip">{{ $f($t['km']) }} km<small>Distance parcourue</small></div>
                </div>

                {{-- Cascade de rentabilité --}}
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
                    <div class="cg-titre">Cascade de rentabilité</div>
                    <div class="cg-pal">
                        @foreach ($etapes as $e)
                            @php $c = $loop->first ? '#0ea5e9' : ($e[1] >= 0 ? '#16a34a' : '#dc2626'); @endphp
                            @if ($e[2])
                                <div class="cg-moins">{{ $e[2] }}</div>
                            @endif
                            <div class="cg-pal-l"><span>{{ $e[0] }}</span><b style="color: {{ $c }}">{{ $s($e[1]) }} FCFA</b></div>
                            <div class="cg-bar"><i style="width: {{ round(min(100, abs($e[1]) / $base * 100), 1) }}%; background: {{ $c }}"></i></div>
                        @endforeach
                    </div>
                </div>

                {{-- Points d'attention --}}
                @if ($d['alertes'])
                    <div>
                        <div class="cg-titre">Points d’attention</div>
                        <div class="cg-alertes">
                            @foreach ($d['alertes'] as $al)
                                <div class="cg-alerte" style="--c: {{ ['danger' => '#dc2626', 'warning' => '#f59e0b', 'info' => '#0ea5e9'][$al[0]] }}">
                                    <b>{{ $al[1] }}</b><span>{{ $al[2] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Écart carburant --}}
                @php $ec = $a['ecart_carburant']; @endphp
                @if ($ec['standard'] > 0 || $ec['reel'] > 0)
                    @php
                        $pctEc = $ec['standard'] > 0 ? $ec['ecart'] / $ec['standard'] * 100 : 0;
                        $hors = abs($pctEc) > $a['parametres']['ecart_seuil_pct'];
                    @endphp
                    <div>
                        <div class="cg-titre">Écart carburant : réel contre standard</div>
                        <div class="cg-pal">
                            <div class="cg-pal-l"><span>Carburant réel (départs avec plein saisi)</span><b>{{ $f($ec['reel']) }} FCFA</b></div>
                            <div class="cg-pal-l"><span>Coût standard ({{ $a['parametres']['norme_l100'] }} L/100 km × {{ $f($a['parametres']['prix_litre']) }} FCFA)</span><b>{{ $f($ec['standard']) }} FCFA</b></div>
                            <div class="cg-pal-l"><span>Écart</span><b style="color: {{ $hors ? '#dc2626' : '#16a34a' }}">{{ $s($ec['ecart']) }} FCFA ({{ $p($pctEc) }})</b></div>
                            <div class="cg-pal-l"><span>dont effet prix</span><b>{{ $s($ec['effet_prix']) }} FCFA</b></div>
                            <div class="cg-pal-l"><span>dont effet quantité (consommation)</span><b>{{ $s($ec['effet_quantite']) }} FCFA</b></div>
                        </div>
                    </div>
                @endif

                {{-- Cartes par car, ligne, gare, activité --}}
                @php
                    $onglets = [
                        'cars' => ['Cars', count($a['par_car'])],
                        'lignes' => ['Lignes', count($a['par_ligne'])],
                        'gares' => ['Gares', count($a['par_gare'])],
                        'activites' => ['Activités', count($a['par_activite'])],
                    ];
                    $liste = match ($this->vue) {
                        'lignes' => $a['par_ligne'],
                        'gares' => $a['par_gare'],
                        'activites' => $a['par_activite'],
                        default => $a['par_car'],
                    };
                    $groupes = [
                        'Recettes' => ['recettes_app' => 'Billets de l’application', 'recettes_guichet' => 'Guichet', 'recettes_colis' => 'Colis et bagages'],
                        'Coûts variables' => ['carburant' => 'Carburant ou énergie', 'route' => 'Frais de route et péages', 'commissions' => 'Commissions de paiement'],
                        'Coûts directs' => ['equipage' => 'Équipage', 'maintenance' => 'Maintenance', 'pneus' => 'Pneumatiques', 'fixes_car' => 'Coûts fixes du car', 'incidents' => 'Incidents et amendes'],
                        'Gares et siège' => ['gare' => 'Coûts de gare et d’agence', 'siege' => 'Siège et atelier'],
                    ];
                @endphp
                <div class="cg-vues">
                    @foreach ($onglets as $cle => $o)
                        <button type="button" @class(['cg-vue', 'cg-vue-on' => $this->vue === $cle]) wire:key="onglet-{{ $cle }}" wire:click="choisirVue('{{ $cle }}')">
                            <span class="cg-vue-n">{{ $o[1] }}</span><span>{{ $o[0] }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="cg-grille">
                    @foreach ($liste as $x)
                        @php
                            $res = $x['resultat'];
                            [$c, $verdict] = match (true) {
                                $x['recettes'] <= 0 => ['#6b7280', 'Sans recette'],
                                $res < 0 => ['#dc2626', 'Déficitaire'],
                                ($x['marge_pct'] ?? 0) >= 10 => ['#16a34a', 'Rentable'],
                                default => ['#f59e0b', 'Fragile'],
                            };
                            $r = $x['remplissage'] ?? null;
                        @endphp
                        <div class="cg-carte" style="--c: {{ $c }}" wire:key="carte-{{ $this->vue }}-{{ $loop->index }}" x-data="{ o: false }">
                            <div class="cg-carte-h">
                                <span class="cg-carte-n">{{ $x['libelle'] }}</span>
                                <span class="cg-pastille">{{ $verdict }}</span>
                            </div>
                            <div class="cg-res">{{ $s($res) }} FCFA <small>{{ $p($x['marge_pct']) }}</small></div>
                            <div class="cg-mini">
                                <div>{{ $f($x['recettes']) }}<small>Recettes</small></div>
                                <div>{{ $f($x['couts_total']) }}<small>Coûts</small></div>
                                @isset($x['voyages'])
                                    <div>{{ $x['voyages'] }}<small>Départ(s)</small></div>
                                @endisset
                            </div>
                            @if ($r !== null)
                                <div class="cg-prog"><i style="width: {{ min(100, $r) }}%"></i></div>
                                <div class="cg-immo">Remplissage pondéré : {{ $p($r) }}</div>
                            @endif
                            @if (! empty($x['immobilisation']))
                                <div class="cg-immo">Jours sans voyage (immobilisation) : −{{ $f($x['immobilisation']) }} FCFA, soit un résultat réel de {{ $s($x['resultat_avec_immobilisation']) }} FCFA</div>
                            @endif
                            @isset($x['carburant'])
                                <button type="button" class="cg-btn" x-on:click="o = ! o" x-text="o ? 'Masquer le détail' : 'Voir le détail'"></button>
                                <div class="cg-det" x-show="o" x-cloak>
                                    @foreach ($groupes as $titre => $champs)
                                        @if (collect($champs)->keys()->contains(fn ($k) => ($x[$k] ?? 0) != 0))
                                            <div class="g">{{ $titre }}</div>
                                            @foreach ($champs as $k => $lib)
                                                @if (($x[$k] ?? 0) != 0)
                                                    <div><span>{{ $lib }}</span><span>{{ $titre === 'Recettes' ? '' : '−' }}{{ $f($x[$k]) }}</span></div>
                                                @endif
                                            @endforeach
                                        @endif
                                    @endforeach
                                </div>
                            @endisset
                        </div>
                    @endforeach
                </div>

                {{-- Ce qui n'est rattaché à aucun départ --}}
                @php
                    $h = $a['hors_departs'];
                    $lignesHors = $h ? array_filter([
                        'Dépenses de cars sans voyage sur la période' => array_sum($h['postes']),
                        'Charges de gare sans départ concerné' => $h['gare'],
                        'Frais de siège sans départ concerné' => $h['siege'],
                        'Jours d’immobilisation des cars (coûts fixes sans voyage)' => $h['immobilisation'],
                    ]) : [];
                @endphp
                @if ($lignesHors)
                    <div>
                        <div class="cg-titre">Hors départs : rien ne disparaît</div>
                        <div class="cg-pal">
                            @foreach ($lignesHors as $lib => $m)
                                <div class="cg-pal-l"><span>{{ $lib }}</span><b>−{{ $f($m) }} FCFA</b></div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif

            {{-- Charges de gares, de siège et d'atelier --}}
            <div>
                <div class="cg-titre">Charges de gares, de siège et d’atelier sur la période</div>
                @if ($d['charges']->isEmpty())
                    <div class="cg-note">Aucune charge saisie : le résultat s’arrête à la contribution des départs. Utilisez « Ajouter une charge » pour y intégrer les loyers, salaires d’agence et frais de siège.</div>
                @else
                    <div class="cg-grille">
                        @foreach ($d['charges'] as $ch)
                            <div class="cg-carte" style="--c: #6366f1" wire:key="charge-{{ $ch->id }}">
                                <div class="cg-carte-h">
                                    <span class="cg-carte-n">{{ $ch->libelle }}</span>
                                    <span class="cg-pastille">{{ \App\Models\ChargeStructure::NIVEAUX[$ch->niveau] ?? $ch->niveau }}</span>
                                </div>
                                <div class="cg-res" style="color: inherit">{{ $f($ch->montant) }} FCFA</div>
                                <div class="cg-immo">
                                    Du {{ $ch->periode_debut->format('d/m/Y') }} au {{ $ch->periode_fin->format('d/m/Y') }}
                                    @if ($ch->gare) · {{ $ch->gare->quartier }} @endif
                                    · réparti selon : {{ config('controle_gestion.cles')[$ch->cle] ?? $ch->cle }}
                                </div>
                                <button type="button" class="cg-btn" wire:click="supprimerCharge({{ $ch->id }})" wire:confirm="Supprimer cette charge ?">Supprimer</button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>