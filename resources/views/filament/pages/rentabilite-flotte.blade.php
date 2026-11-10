<x-filament-panels::page>
    @include('filament.partials.finance-styles')
    @include('filament.partials.flotte-styles')

    @php
        $d = $this->donnees();
        $f = $d['flotte'];
        $c = $d['choisi'];
        $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
        $pct = fn ($n) => $n === null ? '—' : number_format((float) $n, 1, ',', ' ') . ' %';
        $periodes = ['30j' => '30 jours', 'mois' => 'Ce mois', 'mois_dernier' => 'Mois dernier', 'trimestre' => 'Trimestre', 'annee' => 'Année', 'perso' => 'Dates précises'];
        $verdicts = ['rentable' => '#10b981', 'fragile' => '#f59e0b', 'deficitaire' => '#ef4444', 'insuffisant' => '#64748b'];
        $niveaux = ['danger' => '#ef4444', 'warning' => '#f59e0b', 'info' => '#0ea5e9', 'success' => '#10b981'];
        $groupes = \App\Models\DepenseVehicule::GROUPES;
        $couleursGroupes = ['carburant' => '#f59e0b', 'entretien' => '#ef4444', 'route' => '#0ea5e9', 'chauffeur' => '#10b981', 'fixes' => '#6366f1', 'autres' => '#94a3b8'];
    @endphp

    <div class="rf">
        <div class="rf-bar">
            @if (auth()->user()->isSuperAdmin())
                <select wire:model.live="compagnieId" class="rf-select">
                    @foreach ($d['compagnies'] as $id => $nom)
                        <option value="{{ $id }}">{{ $nom }}</option>
                    @endforeach
                </select>
            @endif

            @foreach ($periodes as $cle => $libelle)
                <button type="button" wire:click="$set('periode', '{{ $cle }}')" class="rf-pill {{ $periode === $cle ? 'is-on' : '' }}">{{ $libelle }}</button>
            @endforeach

            @if ($periode === 'perso')
                <input type="date" wire:model.live="du" class="rf-select" style="max-width:170px">
                <input type="date" wire:model.live="au" class="rf-select" style="max-width:170px">
            @endif
        </div>

        <div class="rf-hero" style="background:radial-gradient(800px 380px at 92% -25%,rgba(56,189,248,.55),transparent 60%),linear-gradient(135deg,#0c4a6e 0%,#0369a1 58%,#0284c7 100%)">
            <div class="rf-hero-grid">
                <div>
                    <div class="rf-eyebrow">Résultat de la flotte · {{ $d['nomCompagnie'] }}</div>
                    <div class="rf-big"
                         wire:key="res-{{ $f['resultat'] }}"
                         x-data="{ v: 0, cible: {{ (int) $f['resultat'] }} }"
                         x-init="const s = performance.now(); const tick = (now) => { const k = Math.min(1, (now - s) / 1000); v = Math.round(cible * (1 - Math.pow(1 - k, 3))); if (k < 1) requestAnimationFrame(tick); }; requestAnimationFrame(tick)">
                        <span x-text="v.toLocaleString('fr-FR')">{{ $fmt($f['resultat']) }}</span><small>FCFA</small>
                    </div>
                    <div class="rf-chips">
                        <span class="rf-chip">{{ $d['debut']->format('d/m/Y') }} → {{ $d['fin']->format('d/m/Y') }}</span>
                        <span class="rf-chip">{{ $f['nb_cars'] }} car(s) · {{ $f['voyages'] }} voyage(s)</span>
                        <span class="rf-chip">Marge {{ $pct($f['marge']) }}</span>
                        <span class="rf-chip">Remplissage {{ $pct($f['remplissage']) }}</span>
                    </div>
                </div>
                <div class="rf-grid" style="grid-template-columns:repeat(3,1fr);gap:10px">
                    @foreach ([['Rentables', $f['rentables']], ['Fragiles', $f['fragiles']], ['Déficitaires', $f['deficitaires']]] as [$lib, $val])
                        <div style="padding:12px;border-radius:16px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.25);text-align:center">
                            <div style="font-size:1.8rem;font-weight:900;line-height:1">{{ $val }}</div>
                            <div style="font-size:.64rem;letter-spacing:.1em;text-transform:uppercase;opacity:.85;font-weight:800;margin-top:6px">{{ $lib }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($d['non_rattache']['montant'] > 0)
            <div class="rf-note">
                {{ $fmt($d['non_rattache']['montant']) }} FCFA de billets de l'application ({{ $d['non_rattache']['departs'] }} départ(s)) ne sont rattachés à aucun car : programmez les voyages correspondants pour qu'ils comptent dans la rentabilité.
            </div>
        @endif

        @if (count($d['alertes']) > 0)
            <div class="rf-card">
                <h3>Alertes ({{ count($d['alertes']) }})</h3>
                <div class="fl-liste">
                    @foreach ($d['alertes'] as $a)
                        <div class="fl-conseil" style="--c: {{ $niveaux[$a['niveau']] }}">
                            <div>
                                <h5>{{ $a['type'] }} {{ $a['objet'] }} : {{ $a['document'] }}</h5>
                                <p>
                                    @if ($a['jours'] === null)
                                        À programmer au plus vite.
                                    @elseif ($a['jours'] < 0)
                                        Expiré depuis {{ abs($a['jours']) }} jour(s) ({{ $a['date'] }}).
                                    @else
                                        Expire dans {{ $a['jours'] }} jour(s) ({{ $a['date'] }}).
                                    @endif
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="rf-card">
            <h3>Classement des cars</h3>
            <div class="sub">Cliquez sur un car pour voir son détail et les mesures à prendre.</div>
            <div class="flotte-cars">
                @forelse ($d['cars'] as $x)
                    @php
                        $remplissage = $x['remplissage'] ?? 0;
                        $couleurRemplissage = $remplissage >= 70 ? '#10b981' : ($remplissage >= 50 ? '#f59e0b' : '#ef4444');
                    @endphp
                    <button
                        type="button"
                        class="fl-car-card {{ $c && $c['id'] === $x['id'] ? 'on' : '' }}"
                        wire:click="$set('vehiculeId', '{{ $x['id'] }}')"
                        wire:key="car-{{ $x['id'] }}"
                        aria-pressed="{{ $c && $c['id'] === $x['id'] ? 'true' : 'false' }}"
                    >
                        <span class="fl-car-head">
                            <span>
                                <strong>{{ $x['immatriculation'] }}</strong>
                                <small>{{ $x['compagnie'] }} · {{ $x['places'] }} places · {{ $x['voyages'] }} voyage(s)</small>
                            </span>
                            <span class="fl-verdict" style="--v: {{ $verdicts[$x['verdict']] }}">{{ \App\Services\Flotte\Rentabilite::verdictLibelle($x['verdict']) }}</span>
                        </span>
                        <span class="fl-car-fill">
                            <span class="fl-jauge" style="--c: {{ $couleurRemplissage }}">
                                <i style="width: {{ min(100, (int) $remplissage) }}%"></i>
                            </span>
                            <small>Remplissage <b>{{ $pct($x['remplissage']) }}</b></small>
                        </span>
                        <span class="fl-car-metrics">
                            <span><small>Recettes</small><b>{{ $fmt($x['recettes']) }} FCFA</b></span>
                            <span><small>Dépenses + amort.</small><b>{{ $fmt($x['charges'] + $x['amortissement']) }} FCFA</b></span>
                            <span><small>Résultat</small><b style="color:{{ $x['resultat'] >= 0 ? '#10b981' : '#ef4444' }}">{{ $fmt($x['resultat']) }} FCFA</b></span>
                            <span><small>Marge</small><b>{{ $pct($x['marge']) }}</b></span>
                        </span>
                    </button>
                @empty
                    <p class="flotte-cars-empty">Aucun car pour cette compagnie. Enregistrez vos cars dans Flotte, Cars.</p>
                @endforelse
            </div>
        </div>

        @if ($c)
            <div class="rf-card" wire:key="detail-{{ $c['id'] }}">
                <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between">
                    <div>
                        <h3 style="margin:0">{{ $c['libelle'] }}</h3>
                        <div class="sub" style="margin:2px 0 0">{{ $c['places'] }} places · {{ $c['voyages'] }} voyage(s) · {{ $fmt($c['km']) }} km</div>
                    </div>
                    <span class="fl-verdict" style="--v: {{ $verdicts[$c['verdict']] }};font-size:.85rem;padding:6px 16px">{{ \App\Services\Flotte\Rentabilite::verdictLibelle($c['verdict']) }}</span>
                </div>

                <div class="fl-kpis" style="margin-top:16px">
                    <div class="fl-kpi"><small>Recettes</small><b>{{ $fmt($c['recettes']) }}</b><em>FCFA</em></div>
                    <div class="fl-kpi"><small>Dépenses</small><b>{{ $fmt($c['charges']) }}</b><em>FCFA</em></div>
                    <div class="fl-kpi"><small>Amortissement</small><b>{{ $fmt($c['amortissement']) }}</b><em>FCFA sur la période</em></div>
                    <div class="fl-kpi"><small>Résultat avant amortissement</small><b style="color:{{ $c['resultat_avant_amort'] >= 0 ? '#10b981' : '#ef4444' }}">{{ $fmt($c['resultat_avant_amort']) }}</b><em>FCFA</em></div>
                    <div class="fl-kpi"><small>Résultat</small><b style="color:{{ $c['resultat'] >= 0 ? '#10b981' : '#ef4444' }}">{{ $fmt($c['resultat']) }}</b><em>Marge {{ $pct($c['marge']) }}</em></div>
                    <div class="fl-kpi"><small>Remplissage</small><b>{{ $pct($c['remplissage']) }}</b><em>{{ $c['pax'] }} passagers</em></div>
                    <div class="fl-kpi"><small>Recette par km</small><b>{{ $c['recette_km'] === null ? '—' : $fmt($c['recette_km']) }}</b><em>FCFA</em></div>
                    <div class="fl-kpi"><small>Coût par km</small><b>{{ $c['cout_km'] === null ? '—' : $fmt($c['cout_km']) }}</b><em>FCFA, amortissement compris</em></div>
                    <div class="fl-kpi"><small>Consommation</small><b>{{ $c['l100'] === null ? '—' : number_format($c['l100'], 1, ',', ' ') }}</b><em>L/100 km @if ($f['l100']) · flotte {{ number_format($f['l100'], 1, ',', ' ') }} @endif</em></div>
                    <div class="fl-kpi"><small>Seuil de rentabilité</small><b>{{ $c['seuil_pax'] ?? '—' }}</b><em>passagers par voyage @if ($c['seuil_pct']) · {{ $pct($c['seuil_pct']) }} des places @endif</em></div>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;margin-top:22px">
                    <div>
                        <h4 style="margin:0 0 8px;font-weight:800">D'où viennent les recettes</h4>
                        @php
                            $recettes = ['Application' => [$c['recettes_app'], '#0ea5e9'], 'Guichet' => [$c['recettes_guichet'], '#10b981'], 'Colis' => [$c['recettes_colis'], '#f59e0b']];
                        @endphp
                        <div class="fl-pile">
                            @foreach ($recettes as $lib => [$m, $col])
                                @if ($c['recettes'] > 0 && $m > 0)<i style="width: {{ $m / $c['recettes'] * 100 }}%;background:{{ $col }}" title="{{ $lib }}"></i>@endif
                            @endforeach
                        </div>
                        <div class="fl-legende">
                            @foreach ($recettes as $lib => [$m, $col])
                                <span style="--c: {{ $col }}">{{ $lib }} : {{ $fmt($m) }}</span>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <h4 style="margin:0 0 8px;font-weight:800">Où partent les dépenses</h4>
                        <div class="fl-pile">
                            @foreach ($c['par_groupe'] as $g => $m)
                                @if ($c['charges'] > 0 && $m > 0)<i style="width: {{ $m / $c['charges'] * 100 }}%;background:{{ $couleursGroupes[$g] ?? '#94a3b8' }}" title="{{ $groupes[$g] ?? $g }}"></i>@endif
                            @endforeach
                        </div>
                        <div class="fl-legende">
                            @forelse ($c['par_groupe'] as $g => $m)
                                <span style="--c: {{ $couleursGroupes[$g] ?? '#94a3b8' }}">{{ $groupes[$g] ?? $g }} : {{ $fmt($m) }}</span>
                            @empty
                                <span style="--c:#94a3b8">Aucune dépense enregistrée</span>
                            @endforelse
                        </div>
                    </div>
                </div>

                @if ($c['amortissable'])
                    <div class="rf-note" style="margin-top:18px">
                        Prix d'achat {{ $fmt($c['prix']) }} FCFA · déjà amorti {{ $fmt($c['amort_cumule']) }} FCFA · valeur nette {{ $fmt($c['vnc']) }} FCFA · fin d'amortissement le {{ $c['fin_amortissement'] }}.
                    </div>
                @endif
            </div>

            <div class="rf-card">
                <h3>Mesures à prendre</h3>
                <div class="fl-liste">
                    @forelse ($c['conseils'] as $x)
                        <div class="fl-conseil" style="--c: {{ $niveaux[$x['niveau']] }}">
                            <div><h5>{{ $x['titre'] }}</h5><p>{{ $x['texte'] }}</p></div>
                        </div>
                    @empty
                        <div class="sub">Rien à signaler.</div>
                    @endforelse
                </div>
            </div>

            <div class="rf-two">
                <div class="rf-card">
                    <h3>Lignes et horaires</h3>
                    <div class="sub">Du moins rempli au mieux rempli.</div>
                    <div class="fl-detail-cards">
                        @forelse ($c['lignes'] as $l)
                            @php
                                $remplissageLigne = $l['remplissage'] ?? 0;
                                $couleurLigne = $remplissageLigne >= 70 ? '#10b981' : ($remplissageLigne >= 50 ? '#f59e0b' : '#ef4444');
                            @endphp
                            <article class="fl-detail-card">
                                <div class="fl-detail-head">
                                    <div><h4>{{ $l['route'] }}</h4><small>Départ à {{ $l['heure'] }}</small></div>
                                    <b>{{ $l['voyages'] }} voyage(s)</b>
                                </div>
                                <div class="fl-jauge" style="--c: {{ $couleurLigne }}">
                                    <i style="width: {{ min(100, (int) $remplissageLigne) }}%"></i>
                                </div>
                                <div class="fl-detail-metrics">
                                    <span>Remplissage <strong>{{ $pct($l['remplissage']) }}</strong></span>
                                    <span>Recette moyenne <strong>{{ $fmt($l['recette_moyenne']) }} FCFA</strong></span>
                                </div>
                            </article>
                        @empty
                            <p class="flotte-cars-empty">Aucun voyage.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rf-card">
                    <h3>Chauffeurs</h3>
                    <div class="sub">Consommation estimée à partir des pleins saisis à la clôture des voyages.</div>
                    <div class="fl-detail-cards">
                        @forelse ($c['chauffeurs'] as $x)
                            <article class="fl-detail-card">
                                <div class="fl-detail-head"><h4>{{ $x['nom'] }}</h4><b>{{ $x['voyages'] }} voyage(s)</b></div>
                                <div class="fl-detail-metrics">
                                    <span>Kilométrage <strong>{{ $fmt($x['km']) }} km</strong></span>
                                    <span>Consommation <strong>{{ $x['l100'] === null ? '—' : number_format($x['l100'], 1, ',', ' ').' L/100 km' }}</strong></span>
                                </div>
                            </article>
                        @empty
                            <p class="flotte-cars-empty">Aucun voyage.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>