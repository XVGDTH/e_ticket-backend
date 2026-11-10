<x-filament-panels::page>
    <div class="gx">
        @include('filament.partials.gx-style')

        @php
            $f = fn ($n) => number_format((int) $n, 0, ',', ' ');
            $s = fn ($n) => ((int) $n < 0 ? '−' : ($n > 0 ? '+' : '')).number_format(abs((int) $n), 0, ',', ' ');
            $e = $r['ecarts'] ?? null;
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
                <div class="gx-titre">Écarts par compagnie · {{ ucfirst($mois_noms[(int) $this->mois]) }} {{ $this->annee }}</div>
                <div class="gx-grille">
                    @foreach ($rapports as $compagnieId => $rapport)
                        @php
                            $totalEcart = $rapport['ecarts']['total'] ?? null;
                            $resultatReel = $rapport['reel']['total']['resultat'];
                        @endphp
                        <article class="gx-carte" style="--c: {{ $totalEcart && $totalEcart['ecart']['resultat'] < 0 ? $rouge : $vert }}" wire:key="ecart-compagnie-{{ $compagnieId }}">
                            <div class="gx-n">{{ $compagnieNoms[$compagnieId] ?? 'Compagnie' }}</div>
                            @if ($totalEcart)
                                <div class="gx-res">{{ $s($totalEcart['ecart']['resultat']) }} FCFA</div>
                                <div class="gx-s">Écart de résultat · réel {{ $f($resultatReel) }} FCFA · {{ $totalEcart['reel']['departs'] }} départ(s)</div>
                            @else
                                <div class="gx-s">Aucun budget validé pour cette période · résultat réel {{ $f($resultatReel) }} FCFA.</div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        @elseif (! $r)
            <div class="gx-vide">Aucune compagnie à analyser pour votre compte.</div>
        @elseif (! $e)
            <div class="gx-vide">Aucun budget validé pour {{ $r['annee'] }}. Créez puis validez un budget dans <a href="{{ \App\Filament\Pages\Budget::getUrl() }}" style="text-decoration:underline">Budget</a> : il devient la référence des écarts.</div>
        @else
            @php
                $t = $e['total'];
                $resBud = $t['budget']['resultat'];
                $resRel = $t['reel']['resultat'];
                $ecart = $t['ecart']['resultat'];
                $ec = $r['reel']['ecart_carburant'];
            @endphp

            <div class="gx-note">
                Budget : {{ $r['budget']['libelle'] }} (version {{ $r['budget']['version'] }}, validé le {{ $r['budget']['valide_le'] }})
                · seuil d’écart {{ rtrim(rtrim(number_format($e['seuil'], 1, ',', ' '), '0'), ',') }} %
                @if ($r['partiel']) · mois en cours : budget proratisé à {{ round($e['coef'] * 100) }} % (jours écoulés) @endif
                @if ($cloture) · mois clôturé : commentaires figés @endif
            </div>

            <div class="gx-kpis">
                <div class="gx-kpi gx-bleu"><div class="gx-kpi-t">Résultat budgété</div><div class="gx-kpi-v">{{ $f($resBud) }} FCFA</div><div class="gx-kpi-s">{{ $t['budget']['departs'] }} départ(s) prévus</div></div>
                <div class="gx-kpi gx-gris"><div class="gx-kpi-t">Résultat réel</div><div class="gx-kpi-v">{{ $f($resRel) }} FCFA</div><div class="gx-kpi-s">{{ $t['reel']['departs'] }} départ(s) effectués</div></div>
                <div class="gx-kpi {{ $ecart >= 0 ? 'gx-vert' : 'gx-rouge' }}"><div class="gx-kpi-t">Écart sur le résultat</div><div class="gx-kpi-v">{{ $s($ecart) }} FCFA</div><div class="gx-kpi-s">{{ $ecart >= 0 ? 'Favorable' : 'Défavorable' }}</div></div>
                <div class="gx-kpi {{ $e['manquants'] > 0 ? 'gx-rouge' : 'gx-vert' }}"><div class="gx-kpi-t">Commentaires manquants</div><div class="gx-kpi-v">{{ $e['manquants'] }}</div><div class="gx-kpi-s">{{ $e['manquants'] > 0 ? 'Requis avant la diffusion du reporting' : 'Rien à expliquer de plus' }}</div></div>
            </div>

            <div>
                <div class="gx-titre">Écart de recettes décomposé</div>
                <div class="gx-pal">
                    <div class="gx-pal-l"><span>Effet prix (recette moyenne par voyageur)</span><b style="color: {{ $t['effets']['prix'] >= 0 ? $vert : $rouge }}">{{ $s($t['effets']['prix']) }} FCFA</b></div>
                    <div class="gx-pal-l"><span>Effet volume (plus ou moins de voyageurs)</span><b style="color: {{ $t['effets']['volume'] >= 0 ? $vert : $rouge }}">{{ $s($t['effets']['volume']) }} FCFA</b></div>
                    <div class="gx-pal-l"><span>Effet composition (répartition entre les lignes)</span><b style="color: {{ $t['effets']['composition'] >= 0 ? $vert : $rouge }}">{{ $s($t['effets']['composition']) }} FCFA</b></div>
                    <div class="gx-pal-l"><span>Messagerie, non budgétée</span><b style="color: {{ $t['ecart']['colis'] >= 0 ? $vert : $rouge }}">{{ $s($t['ecart']['colis']) }} FCFA</b></div>
                    @if ($t['reel']['hors'] > 0)
                        <div class="gx-moins">Le résultat réel inclut {{ $f($t['reel']['hors']) }} FCFA de charges hors départs (jours d’immobilisation des cars, charges sans départ).</div>
                    @endif
                </div>
            </div>

            @if ($ec['standard'] > 0)
                <div>
                    <div class="gx-titre">Carburant : réel contre standard</div>
                    <div class="gx-pal">
                        <div class="gx-pal-l"><span>Écart total</span><b style="color: {{ $ec['ecart'] <= 0 ? $vert : $rouge }}">{{ $s($ec['ecart']) }} FCFA</b></div>
                        <div class="gx-pal-l"><span>dont effet prix du litre</span><b>{{ $s($ec['effet_prix']) }} FCFA</b></div>
                        <div class="gx-pal-l"><span>dont effet consommation</span><b>{{ $s($ec['effet_quantite']) }} FCFA</b></div>
                    </div>
                </div>
            @endif

            @php
                $cartes = [...$e['lignes'], $t];
                $postes = \App\Services\ControleGestion\Reporting::LIBELLES;
                $couts = array_diff(array_keys($postes), ['recettes', 'colis']);
            @endphp

            <div>
                <div class="gx-titre">Écarts par ligne, les plus défavorables d’abord</div>
                <div class="gx-grille">
                    @foreach ($cartes as $l)
                        @php
                            $c = $l['ecart']['resultat'] >= 0 ? $vert : $rouge;
                            $texte = $l['commentaire'];
                        @endphp
                        <div class="gx-carte" style="--c: {{ $c }}" wire:key="ecart-{{ $l['cle'] }}" x-data="{ o: false }">
                            <div class="gx-h">
                                <span class="gx-n">{{ $l['libelle'] }}</span>
                                @if ($l['requis'])
                                    <span class="gx-pastille" style="--c: {{ $texte ? '#6366f1' : '#dc2626' }}">{{ $texte ? 'Commenté' : 'Commentaire requis' }}</span>
                                @endif
                            </div>
                            <div class="gx-res">{{ $s($l['ecart']['resultat']) }} FCFA</div>
                            <div class="gx-mini">
                                <div>{{ $f($l['budget']['resultat']) }}<small>Résultat budget</small></div>
                                <div>{{ $f($l['reel']['resultat']) }}<small>Résultat réel</small></div>
                                <div>{{ $l['reel']['departs'] }} / {{ $l['budget']['departs'] }}<small>Départs réel / budget</small></div>
                            </div>
                            @if ($texte)
                                <div class="gx-com">{{ $texte }}</div>
                            @endif
                            <div class="gx-btns">
                                @if (! $cloture && ($l['requis'] || $texte))
                                    <button type="button" class="gx-btn" wire:click="mountAction('commenter', { cle: '{{ $l['cle'] }}' })">{{ $texte ? 'Modifier le commentaire' : 'Commenter' }}</button>
                                @endif
                                <button type="button" class="gx-btn" x-on:click="o = ! o" x-text="o ? 'Masquer le détail' : 'Voir le détail'"></button>
                            </div>
                            <div class="gx-det" x-show="o" x-cloak>
                                <div class="g"><span>Poste</span><span>Budget</span><span>Réel</span><span>Écart</span></div>
                                @foreach (['recettes', 'colis', ...$couts] as $k)
                                    @if ($l['budget'][$k] != 0 || $l['reel'][$k] != 0)
                                        @php $favorable = in_array($k, ['recettes', 'colis'], true) ? $l['ecart'][$k] >= 0 : $l['ecart'][$k] <= 0; @endphp
                                        <div><span>{{ $postes[$k] }}</span><span>{{ $f($l['budget'][$k]) }}</span><span>{{ $f($l['reel'][$k]) }}</span><span class="e" style="color: {{ $favorable ? $vert : $rouge }}">{{ $s($l['ecart'][$k]) }}</span></div>
                                    @endif
                                @endforeach
                                @if ($l['reel']['hors'] > 0)
                                    <div><span>Hors départs</span><span>0</span><span>{{ $f($l['reel']['hors']) }}</span><span class="e" style="color: {{ $rouge }}">{{ $s($l['reel']['hors']) }}</span></div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>