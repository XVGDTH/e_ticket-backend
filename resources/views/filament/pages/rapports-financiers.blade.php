<x-filament-panels::page>
    @include('filament.partials.finance-styles')

    @php
        $d = $this->donnees();
        $t = $d['t'];
        $p = $d['p'];
        $o = $d['options'];
        $superAdmin = auth()->user()->isSuperAdmin();

        $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
        $delta = fn ($a, $b) => (float) $b > 0 ? round(((float) $a - (float) $b) / (float) $b * 100, 1) : null;
        $chip = function ($v) {
            if ($v === null) { return ['flat', 'Pas de période précédente']; }
            if (abs($v) < 1) { return ['flat', '≈ stable vs période précédente']; }
            return $v > 0
                ? ['up', '▲ +' . number_format($v, 1, ',', ' ') . ' % vs période précédente']
                : ['down', '▼ ' . number_format($v, 1, ',', ' ') . ' % vs période précédente'];
        };
        [, $txtHero] = $chip($delta($t->total, $p->total));
        $panier = fn ($x) => (int) $x->billets > 0 ? $x->total / $x->billets : 0;

        // Courbe du hero
        $vals = $d['serie']->pluck('total')->map(fn ($v) => (float) $v)->all();
        $n = max(count($vals), 1);
        $max = max($vals ?: [1]) ?: 1;
        $pts = [];
        foreach ($vals as $i => $v) {
            $x = $n > 1 ? $i / ($n - 1) * 300 : 150;
            $y = 64 - ($v / $max) * 54;
            $pts[] = round($x, 1) . ',' . round($y, 1);
        }
        $ligne = implode(' ', $pts);
        $aire = $ligne ? '0,70 ' . $ligne . ' 300,70' : '';

        $tuiles = [
            ['Prix des billets', $t->ca, ' FCFA', 'heroicon-o-ticket', $delta($t->ca, $p->ca)],
            ['Frais collectés', $t->frais, ' FCFA', 'heroicon-o-receipt-percent', $delta($t->frais, $p->frais)],
            ['Billets', $t->billets, '', 'heroicon-o-qr-code', $delta($t->billets, $p->billets)],
            ['Voyageurs', $t->voyageurs, '', 'heroicon-o-users', $delta($t->voyageurs, $p->voyageurs)],
            ['Panier moyen', $panier($t), ' FCFA', 'heroicon-o-shopping-bag', $delta($panier($t), $panier($p))],
            ['Clients actifs', $t->clients, '', 'heroicon-o-user-group', $delta($t->clients, $p->clients)],
        ];

        $pctPrix = (float) $t->total > 0 ? round($t->ca / $t->total * 100, 1) : 0;
        $pctFrais = (float) $t->total > 0 ? round(100 - $pctPrix, 1) : 0;

        // Podium des gares
        $gares = $d['gares'];
        $maxGare = max((float) ($gares->first()->total ?? 0), 1);
        $top = $gares->take(3)->values();
        $ordre = [1, 0, 2];
        $hauteurs = [0 => 100, 1 => 76, 2 => 58];
        $medailles = ['🥇', '🥈', '🥉'];

        // Anneau des opérateurs
        $couleurs = ['#14b8a6', '#f59e0b', '#6366f1', '#ec4899', '#22c55e', '#0ea5e9'];
        $totalOp = max((float) $d['operateurs']->sum('total'), 1);
        $cum = 0;
        $segments = [];
        foreach ($d['operateurs'] as $i => $op) {
            $pct = (float) $op->total / $totalOp * 100;
            $segments[] = ['pct' => $pct, 'off' => 25 - $cum, 'c' => $couleurs[$i % count($couleurs)], 'lib' => $op->libelle, 'tot' => $op->total];
            $cum += $pct;
        }

        // Calendrier de chaleur
        $debutHeat = now()->startOfWeek()->subWeeks(11)->startOfDay();
        $maxHeat = max(1, (float) collect($d['heat'])->max());
    @endphp

    <div class="rf">
        {{-- Filtres --}}
        <div class="rf-bar">
            @foreach (\App\Services\Finance\RapportFinancier::PERIODES as $cle => $lib)
                <button type="button" wire:click="$set('periode', '{{ $cle }}')" class="rf-pill {{ $periode === $cle ? 'is-on' : '' }}">{{ $lib }}</button>
            @endforeach

            @if ($periode === 'perso')
                <input type="date" wire:model.live="debut" class="rf-date">
                <input type="date" wire:model.live="fin" class="rf-date">
            @endif

            <span class="rf-sep"></span>

            @if ($superAdmin)
                <select wire:model.live="compagnieId" class="rf-select">
                    <option value="">Toutes les compagnies</option>
                    @foreach ($o['compagnies'] as $id => $nom)
                        <option value="{{ $id }}">{{ $nom }}</option>
                    @endforeach
                </select>
            @endif

            <select wire:model.live="gareId" class="rf-select">
                <option value="">Toutes les gares</option>
                @foreach ($o['gares'] as $id => $nom)
                    <option value="{{ $id }}">{{ $nom }}</option>
                @endforeach
            </select>

            <select wire:model.live="operateur" class="rf-select">
                <option value="">Tous les opérateurs</option>
                @foreach ($o['operateurs'] as $nom)
                    <option value="{{ $nom }}">{{ $nom }}</option>
                @endforeach
            </select>

            <select wire:model.live="statut" class="rf-select">
                <option value="encaisses">Billets encaissés</option>
                <option value="tous">Tous les paiements</option>
                <option value="rembourse">Remboursés</option>
            </select>
        </div>

        {{-- Hero --}}
        <div class="rf-hero">
            <div class="rf-hero-grid">
                <div>
                    <div class="rf-eyebrow">Total encaissé · {{ $d['perimetre'] }}</div>
                    <div class="rf-big"
                         wire:key="hero-{{ (int) $t->total }}-{{ $periode }}"
                         x-data="{ v: 0, cible: {{ (int) $t->total }} }"
                         x-init="const s = performance.now(); const tick = (now) => { const k = Math.min(1, (now - s) / 1000); v = Math.round(cible * (1 - Math.pow(1 - k, 3))); if (k < 1) requestAnimationFrame(tick); }; requestAnimationFrame(tick)">
                        <span x-text="v.toLocaleString('fr-FR')">{{ $fmt($t->total) }}</span><small>FCFA</small>
                    </div>
                    <div class="rf-chips">
                        <span class="rf-chip">{{ $txtHero }}</span>
                        <span class="rf-chip">{{ $d['f']['debut']->format('d/m/Y') }} → {{ $d['f']['fin']->format('d/m/Y') }}</span>
                    </div>
                </div>
                <div>
                    <svg viewBox="0 0 300 70" preserveAspectRatio="none" class="rf-spark">
                        <defs>
                            <linearGradient id="rfg" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0" stop-color="#fff" stop-opacity=".5"/>
                                <stop offset="1" stop-color="#fff" stop-opacity="0"/>
                            </linearGradient>
                        </defs>
                        <polygon points="{{ $aire }}" fill="url(#rfg)"/>
                        <polyline points="{{ $ligne }}" fill="none" stroke="#fff" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
                    </svg>
                    <div class="rf-spark-cap"><span>Évolution du total encaissé</span><span>{{ count($vals) }} points</span></div>
                </div>
            </div>
        </div>

        {{-- Indicateurs --}}
        <div class="rf-grid">
            @foreach ($tuiles as [$lib, $val, $suffixe, $icone, $dl])
                @php [$cl, $tx] = $chip($dl); @endphp
                <div class="rf-kpi">
                    <div class="rf-ico"><x-filament::icon :icon="$icone" style="width:22px;height:22px" /></div>
                    <div class="rf-kpi-l">{{ $lib }}</div>
                    <div class="rf-kpi-v">{{ $fmt($val) }}<small>{{ $suffixe }}</small></div>
                    <div class="rf-delta {{ $cl }}">{{ $tx }}</div>
                </div>
            @endforeach
        </div>

        @if ((int) $d['remb']->billets > 0)
            <div class="rf-note">
                ↩︎ {{ $fmt($d['remb']->billets) }} billet(s) remboursé(s) sur la période, pour {{ $fmt($d['remb']->montant) }} FCFA. Ils ne sont pas comptés dans les totaux ci-dessus.
            </div>
        @endif

        {{-- Prix / frais + opérateurs --}}
        <div class="rf-two">
            <div class="rf-card">
                <h3>Où va l'argent ?</h3>
                <div class="sub">Répartition du total encaissé entre le prix des billets et les frais.</div>
                <div class="rf-split">
                    <div style="width: {{ max($pctPrix, 4) }}%; background: linear-gradient(135deg,#0f766e,#14b8a6)">{{ $pctPrix }} %</div>
                    <div style="width: {{ max($pctFrais, 4) }}%; background: linear-gradient(135deg,#f59e0b,#d97706)">{{ $pctFrais }} %</div>
                </div>
                <div class="rf-legend">
                    <span><i style="background:#14b8a6"></i>Prix des billets · {{ $fmt($t->ca) }} FCFA</span>
                    <span><i style="background:#f59e0b"></i>Frais · {{ $fmt($t->frais) }} FCFA</span>
                </div>
            </div>

            <div class="rf-card">
                <h3>Opérateurs de paiement</h3>
                <div class="sub">Part du total encaissé.</div>
                <div class="rf-donut">
                    <div style="position:relative;width:170px;height:170px">
                        <svg viewBox="0 0 42 42">
                            <circle cx="21" cy="21" r="15.9155" fill="none" stroke="var(--soft)" stroke-width="5"/>
                            @foreach ($segments as $sg)
                                <circle cx="21" cy="21" r="15.9155" fill="none" stroke="{{ $sg['c'] }}" stroke-width="5"
                                        stroke-dasharray="{{ round($sg['pct'], 2) }} {{ round(100 - $sg['pct'], 2) }}"
                                        stroke-dashoffset="{{ round($sg['off'], 2) }}"/>
                            @endforeach
                        </svg>
                        <div class="ctr"><div>{{ count($segments) }}<div style="font-size:.65rem;color:var(--muted);font-weight:700">opérateur(s)</div></div></div>
                    </div>
                    <div class="rf-legend" style="flex-direction:column;margin:0">
                        @forelse ($segments as $sg)
                            <span><i style="background:{{ $sg['c'] }}"></i>{{ $sg['lib'] }} · {{ round($sg['pct']) }} % · {{ $fmt($sg['tot']) }} FCFA</span>
                        @empty
                            <span>Aucune vente sur la période.</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Gares et compagnies --}}
        <div class="rf-two">
            <div class="rf-card">
                <h3>Podium des gares</h3>
                <div class="sub">Total encaissé par gare de départ.</div>

                @if ($top->isEmpty())
                    <div class="sub">Aucune vente sur la période.</div>
                @else
                    <div class="rf-podium">
                        @foreach ($ordre as $idx)
                            @if (isset($top[$idx]))
                                <div class="rf-pod p{{ $idx + 1 }}">
                                    <div class="nom">{{ $top[$idx]->libelle }}</div>
                                    <div class="box" style="height: {{ $hauteurs[$idx] }}%">
                                        <span class="med">{{ $medailles[$idx] }}</span>
                                        <span class="val">{{ $fmt($top[$idx]->total) }}</span>
                                        <span class="val" style="opacity:.8">FCFA</span>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    @foreach ($gares->slice(3, 7)->values() as $i => $gare)
                        <div class="rf-row">
                            <div class="rf-rank">{{ $i + 4 }}</div>
                            <div>
                                {{ $gare->libelle }}
                                <div class="rf-track"><i style="width: {{ round($gare->total / $maxGare * 100) }}%"></i></div>
                            </div>
                            <div class="rf-val">{{ $fmt($gare->total) }}<small>dont {{ $fmt($gare->frais) }} de frais</small></div>
                        </div>
                    @endforeach
                @endif
            </div>

            <div class="rf-card">
                <h3>{{ $superAdmin ? 'Compagnies' : 'Ma compagnie' }}</h3>
                <div class="sub">Prix des billets et frais collectés.</div>
                @php $maxComp = max((float) ($d['compagnies']->first()->total ?? 0), 1); @endphp
                @forelse ($d['compagnies'] as $i => $comp)
                    <div class="rf-row">
                        <div class="rf-rank">{{ $i + 1 }}</div>
                        <div>
                            {{ $comp->libelle }}
                            <div class="rf-track"><i style="width: {{ round($comp->total / $maxComp * 100) }}%"></i></div>
                        </div>
                        <div class="rf-val">{{ $fmt($comp->total) }}<small>{{ $fmt($comp->ca) }} + {{ $fmt($comp->frais) }} frais</small></div>
                    </div>
                @empty
                    <div class="sub">Aucune vente sur la période.</div>
                @endforelse
            </div>
        </div>

        {{-- Calendrier --}}
        <div class="rf-card">
            <h3>Le pouls des ventes</h3>
            <div class="sub">Chaque carré est un jour, sur les 12 dernières semaines : plus il est foncé, plus la journée a rapporté.</div>
            <div class="rf-heat">
                @for ($i = 0; $i < 84; $i++)
                    @php
                        $jour = $debutHeat->copy()->addDays($i);
                        $v = (float) ($d['heat'][$jour->toDateString()] ?? 0);
                        $niv = $jour->isFuture() ? 'f' : ($v <= 0 ? 0 : min(4, (int) ceil($v / $maxHeat * 4)));
                    @endphp
                    <i class="rf-cell l{{ $niv }}" title="{{ $jour->format('d/m/Y') }} · {{ $fmt($v) }} FCFA"></i>
                @endfor
            </div>
        </div>

        {{-- Détail par période --}}
        <div class="rf-card">
            <h3>Détail par période</h3>
            <div class="sub">Regroupement automatique : {{ ['jour' => 'par jour', 'semaine' => 'par semaine', 'mois' => 'par mois', 'annee' => 'par année'][$d['granularite']] }}.</div>
            <div class="rf-scroll">
                <table class="rf-table">
                    <thead>
                        <tr><th>Période</th><th class="n">Billets</th><th class="n">Voyageurs</th><th class="n">Prix des billets</th><th class="n">Frais</th><th class="n">Total</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($d['serie']->reverse() as $ligneP)
                            <tr>
                                <td>{{ \App\Services\Finance\RapportFinancier::libellePeriode($d['granularite'], (string) $ligneP->libelle) }}</td>
                                <td class="n">{{ $fmt($ligneP->billets) }}</td>
                                <td class="n">{{ $fmt($ligneP->voyageurs) }}</td>
                                <td class="n">{{ $fmt($ligneP->ca) }}</td>
                                <td class="n">{{ $fmt($ligneP->frais) }}</td>
                                <td class="n"><strong>{{ $fmt($ligneP->total) }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>