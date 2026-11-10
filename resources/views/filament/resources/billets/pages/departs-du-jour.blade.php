<x-filament-panels::page>
    <style>
        .dj-barre { display:flex; align-items:center; justify-content:space-between; gap:.75rem; flex-wrap:wrap; }
        .dj-jour { display:flex; align-items:center; gap:.5rem; font-size:1.05rem; font-weight:600; }
        .dj-statuts { display:grid; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr)); gap:.75rem; }
        .dj-statut { --c:#6b7280; display:flex; flex-direction:column; align-items:flex-start; gap:.1rem; width:100%; padding:.9rem 1rem; text-align:left; cursor:pointer; color:inherit; border-radius:.75rem; border:1px solid color-mix(in srgb, var(--c) 60%, transparent); background:color-mix(in srgb, var(--c) 14%, transparent); }
        .dj-statut:hover { background:color-mix(in srgb, var(--c) 26%, transparent); }
        .dj-statut-n { font-size:1.7rem; font-weight:700; line-height:1.1; color:var(--c); }
        .dj-statut-l { font-size:.85rem; opacity:.85; }
        .dj-liste { display:flex; flex-direction:column; gap:1rem; }
        .dj-compagnie { font-size:1.1rem; font-weight:700; margin-top:.5rem; }
        .dj-gare { border:1px solid rgba(128,128,128,.3); border-radius:.75rem; padding:.85rem 1rem; }
        .dj-gare + .dj-gare { margin-top:.75rem; }
        .dj-gare-tete { display:flex; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
        .dj-gare-nom { display:flex; align-items:center; gap:.35rem; font-weight:600; }
        .dj-prix { font-weight:700; color:#dc2626; }
        .dj-meta { margin-top:.15rem; font-size:.8rem; opacity:.7; }
        .dj-heures { display:flex; flex-wrap:wrap; gap:.5rem; margin-top:.75rem; }
        .dj-heure { display:flex; flex-direction:column; align-items:flex-start; min-width:96px; padding:.45rem .8rem; cursor:pointer; color:inherit; background:transparent; border-radius:.65rem; border:1px solid rgba(128,128,128,.4); text-align:left; }
        .dj-heure:hover { border-color:#f59e0b; }
        .dj-h { font-size:1rem; font-weight:600; }
        .dj-n { font-size:.72rem; opacity:.8; }
        .dj-avec { border-color:rgba(34,197,94,.7); background:rgba(34,197,94,.1); }
        .dj-passe { opacity:.55; }
        .dj-alerte { opacity:1; border-color:rgba(239,68,68,.85); background:rgba(239,68,68,.12); }
        .dj-prochain { box-shadow:0 0 0 2px rgba(245,158,11,.8); }
        .dj-ico { width:1.1rem; height:1.1rem; flex:none; }
    </style>

    @php
        $fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
    @endphp

    {{-- Jour affiché --}}
    <div class="dj-barre">
        <div class="dj-jour">
            {{ $jour->copy()->locale('fr')->translatedFormat('l d F Y') }}
            @if ($jour->isToday())
                <x-filament::badge color="success">Aujourd'hui</x-filament::badge>
            @endif
        </div>

        <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
            <x-filament::button size="sm" color="gray" icon="heroicon-m-chevron-left" wire:click="jourPrecedent">Veille</x-filament::button>
            <x-filament::button size="sm" color="gray" wire:click="aujourdhui">Aujourd'hui</x-filament::button>
            <x-filament::button size="sm" color="gray" icon="heroicon-m-chevron-right" icon-position="after" wire:click="jourSuivant">Lendemain</x-filament::button>
        </div>
    </div>

    {{-- Cartes de statut : un clic ouvre la liste --}}
    <div class="dj-statuts">
        @foreach ($cartesStatut as $cle => $carte)
            <button type="button"
                    class="dj-statut"
                    style="--c: {{ $carte['couleur'] }}"
                    wire:key="statut-{{ $cle }}"
                    wire:click="mountAction('voirBillets', { cle: 'statut:{{ $cle }}' })">
                <span class="dj-statut-n">{{ $fmt($carte['total']) }}</span>
                <span class="dj-statut-l">{{ $carte['label'] }}</span>
            </button>
        @endforeach
    </div>

    {{-- Trajets du jour --}}
    <div class="dj-liste">
        @forelse ($trajets as $compagnie => $groupes)
            @if ($trajets->count() > 1)
                <div class="dj-compagnie" wire:key="compagnie-{{ $compagnie }}">{{ $compagnie }}</div>
            @endif

            @foreach ($groupes as $libelle => $gares)
                <div wire:key="trajet-{{ $compagnie }}-{{ $libelle }}">
                    <x-filament::section :heading="$libelle" icon="heroicon-o-truck">
                        @foreach ($gares as $gare)
                            <div class="dj-gare">
                                <div class="dj-gare-tete">
                                    <div>
                                        <div class="dj-gare-nom">
                                            <x-filament::icon icon="heroicon-o-map-pin" class="dj-ico" />
                                            {{ $gare['gare'] }}
                                        </div>
                                        <div class="dj-meta">
                                            @if ($gare['duree']) Durée max {{ $gare['duree'] }} @endif
                                            @if ($gare['telephone']) · {{ $gare['telephone'] }} @endif
                                        </div>
                                    </div>
                                    <div class="dj-prix">{{ $fmt($gare['prix']) }} FCFA</div>
                                </div>

                                <div class="dj-heures">
                                    @foreach ($gare['chips'] as $chip)
                                        @php
                                            $parts = [];
                                            if ($chip['prets'] > 0) {
                                                $parts[] = $fmt($chip['prets']) . ($chip['alerte'] ? ' non embarqué(s)' : ' prêt(s)');
                                            }
                                            if ($chip['embarques'] > 0) {
                                                $parts[] = $fmt($chip['embarques']) . ' embarqué(s)';
                                            }
                                        @endphp

                                        <button type="button"
                                                @class([
                                                    'dj-heure',
                                                    'dj-avec' => ($chip['prets'] + $chip['embarques']) > 0,
                                                    'dj-passe' => $chip['passe'],
                                                    'dj-alerte' => $chip['alerte'],
                                                    'dj-prochain' => $chip['prochain'],
                                                ])
                                                wire:key="chip-{{ $chip['cle'] }}"
                                                wire:click="mountAction('voirBillets', { cle: 'voyage:{{ $chip['cle'] }}' })">
                                            <span class="dj-h">{{ $chip['label'] }}</span>
                                            <span class="dj-n">{{ $parts ? implode(' · ', $parts) : 'Aucun billet' }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </x-filament::section>
                </div>
            @endforeach
        @empty
            <x-filament::section>
                Aucun trajet programmé ce jour-là pour votre périmètre.
            </x-filament::section>
        @endforelse
    </div>
</x-filament-panels::page>