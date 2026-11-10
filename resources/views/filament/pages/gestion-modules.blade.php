<x-filament-panels::page>
    @include('filament.partials.finance-styles')
    @include('filament.partials.modules-styles')

    @php
        $d = $this->donnees();
        $total = count($d['catalogue']);
        $nb = count(array_intersect(array_keys($d['catalogue']), $d['actives']));
        $pct = $total ? round($nb / $total * 100) : 0;
    @endphp

    <div class="rf">
        <div class="rf-bar">
            <select wire:model.live="compagnieId" class="rf-select">
                @foreach ($d['compagnies'] as $id => $nom)
                    <option value="{{ $id }}">{{ $nom }}</option>
                @endforeach
            </select>
            <span class="rf-sep"></span>
            <button type="button" class="rf-pill" wire:click="toutChanger(true)">Tout activer</button>
            <button type="button" class="rf-pill" wire:click="toutChanger(false)" wire:confirm="Désactiver tous les modules de cette compagnie ?">Tout désactiver</button>
        </div>

        <div class="rf-hero">
            <div class="rf-hero-grid">
                <div>
                    <div class="rf-eyebrow">Centre de pilotage des modules</div>
                    <div class="rf-big">{{ $d['nom'] }}</div>
                    <div class="rf-chips">
                        <span class="rf-chip">{{ $nb }} module(s) activé(s) sur {{ $total }}</span>
                        <span class="rf-chip">L'administrateur de la compagnie les attribue ensuite à chaque gare</span>
                    </div>
                </div>
                <div style="display:grid;place-items:center">
                    <div class="md-ring">
                        <svg viewBox="0 0 42 42">
                            <circle cx="21" cy="21" r="15.9155" fill="none" stroke="rgba(255,255,255,.22)" stroke-width="4"/>
                            <circle cx="21" cy="21" r="15.9155" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round"
                                    stroke-dasharray="{{ $pct }} {{ 100 - $pct }}"/>
                        </svg>
                        <div class="ctr"><div>{{ $pct }} %<small>activé</small></div></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="md-grille">
            @foreach ($d['catalogue'] as $cle => $m)
                @php $on = in_array($cle, $d['actives'], true); @endphp
                <div class="md-carte {{ $on ? 'on' : '' }}" style="--c: {{ $m['couleur'] }}">
                    <div class="md-tete">
                        <div class="md-ico"><x-filament::icon :icon="$m['icone']" style="width:26px;height:26px" /></div>
                        <button type="button" class="md-switch {{ $on ? 'on' : '' }}"
                                wire:click="basculer('{{ $cle }}')" wire:loading.attr="disabled"
                                aria-label="Activer ou désactiver {{ $m['nom'] }}"><i></i></button>
                    </div>
                    <h4>{{ $m['nom'] }}</h4>
                    <p>{{ $m['description'] }}</p>
                    <div class="md-pied">
                        <span class="md-etat">{{ $on ? 'Activé' : 'Désactivé' }}</span>
                        @unless ($m['disponible'])
                            <span class="md-bientot">En préparation</span>
                        @endunless
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>