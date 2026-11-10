<x-filament-panels::page>
    <div class="cs">
        <style>
            .cs { display:flex; flex-direction:column; gap:1.1rem; width:100%; min-width:0; box-sizing:border-box; }
            .cs-filtres { display:flex; flex-wrap:wrap; gap:.6rem; }
            .cs-filtre { min-width:200px; }
            .cs-titre { font-size:.72rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; opacity:.6; margin-bottom:.5rem; }
            .cs-vide { padding:1.5rem; text-align:center; opacity:.75; border:1px dashed rgba(128,128,128,.4); border-radius:1rem; }
            .cs-grille { display:grid; grid-template-columns:repeat(auto-fill, minmax(290px, 1fr)); gap:.8rem; }
            .cs-carte { --c:#6366f1; display:flex; flex-direction:column; gap:.45rem; padding:.9rem 1rem; border-radius:1rem; border:1px solid rgba(128,128,128,.3); border-left:5px solid var(--c); }
            .cs-h { display:flex; justify-content:space-between; align-items:flex-start; gap:.5rem; }
            .cs-n { font-weight:700; line-height:1.25; }
            .cs-pastille { flex:none; padding:.1rem .55rem; font-size:.68rem; font-weight:700; color:#fff; border-radius:999px; background:var(--c); }
            .cs-m { font-size:1.45rem; font-weight:800; }
            .cs-s { font-size:.8rem; opacity:.75; line-height:1.4; }
            .cs-btns { display:flex; gap:.5rem; margin-top:.2rem; }
            .cs-btn { padding:.2rem .75rem; font-size:.78rem; font-weight:600; cursor:pointer; color:inherit; background:transparent; border-radius:999px; border:1px solid rgba(128,128,128,.4); }
            .cs-btn:hover { border-color:#6366f1; }
            .cs-btn-r:hover { border-color:#dc2626; color:#dc2626; }
        </style>

        @php
            $f = fn ($n) => number_format((int) $n, 0, ',', ' ');
            $couleurs = ['gare' => '#0ea5e9', 'siege' => '#6366f1', 'atelier' => '#f59e0b'];
            $debutMois = today()->copy()->startOfMonth();
            $finMois = today()->copy()->endOfMonth();
        @endphp

        <div class="cs-filtres">
            @if ($d['compagnies']->count() > 1)
                <div class="cs-filtre">
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="compagnieId">
                            @foreach ($d['compagnies'] as $id => $nom)
                                <option value="{{ $id }}">{{ $nom }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            @endif
            <div class="cs-filtre">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="portee">
                        @foreach ($d['portees'] as $cle => $nom)
                            <option value="{{ $cle }}">{{ $nom }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>

        @if ($d['charges']->isEmpty())
            <div class="cs-vide">Aucune charge pour cette sélection. Utilisez « Ajouter une charge » pour saisir un loyer, des salaires d’agence ou des frais de siège : ils seront répartis sur les départs et comptés dans la rentabilité.</div>
        @else
            @foreach (\App\Models\ChargeStructure::NIVEAUX as $niveau => $libelleNiveau)
                @php $liste = $d['charges']->get($niveau); @endphp
                @if ($liste && $liste->isNotEmpty())
                    <div>
                        <div class="cs-titre">{{ $libelleNiveau }} · {{ $liste->count() }} charge(s) · {{ $f($liste->sum('montant')) }} FCFA</div>
                        <div class="cs-grille">
                            @foreach ($liste as $c)
                                <div class="cs-carte" style="--c: {{ $couleurs[$niveau] ?? '#6366f1' }}" wire:key="charge-{{ $c->id }}">
                                    <div class="cs-h">
                                        <span class="cs-n">{{ $c->libelle }}</span>
                                        <span class="cs-pastille">{{ $libelleNiveau }}</span>
                                    </div>
                                    <div class="cs-m">{{ $f($c->montant) }} FCFA</div>
                                    <div class="cs-s">
                                        @if ($d['toutesCompagnies'])
                                            <strong>{{ $c->compagnie?->nom ?? 'Compagnie' }}</strong><br>
                                        @endif
                                        Du {{ $c->periode_debut->format('d/m/Y') }} au {{ $c->periode_fin->format('d/m/Y') }}
                                        @if ($c->gare) · {{ $c->gare->quartier }} @endif
                                        <br>Part du mois en cours : {{ $f($c->partPour($debutMois, $finMois)) }} FCFA
                                        <br>Répartie selon : {{ config('controle_gestion.cles')[$c->cle] ?? $c->cle }}
                                        @if ($c->note) <br>{{ $c->note }} @endif
                                    </div>
                                    <div class="cs-btns">
                                        <button type="button" class="cs-btn" wire:click="mountAction('modifier', { id: {{ $c->id }} })">Modifier</button>
                                        <button type="button" class="cs-btn cs-btn-r" wire:click="mountAction('supprimer', { id: {{ $c->id }} })">Supprimer</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        @endif
    </div>
</x-filament-panels::page>