*<div class="ev">
    <style>
        .ev { display:flex; flex-direction:column; gap:1rem; width:100%; min-width:0; box-sizing:border-box; padding:1rem; }
        .ev-vide { padding:1.5rem; text-align:center; opacity:.7; }
        .ev-filtres { display:flex; flex-wrap:wrap; align-items:center; gap:.6rem; }
        .ev-filtre { min-width:230px; }
        .ev-haut { display:grid; grid-template-columns:minmax(230px, 310px) minmax(0, 1fr); gap:1rem; }
        @media (max-width: 760px) { .ev-haut { grid-template-columns:minmax(0, 1fr); } }
        .ev-bloc { color:#fff; border-radius:1rem; padding:1rem 1.25rem; background:linear-gradient(135deg, #f59e0b, #dc2626); box-shadow:0 12px 28px -12px rgba(220,38,38,.65); }
        .ev-live { display:flex; align-items:center; gap:.45rem; font-size:.7rem; font-weight:700; letter-spacing:.12em; }
        .ev-dot { width:.55rem; height:.55rem; border-radius:999px; background:#fff; animation:ev-pulse 1.4s infinite; }
        @keyframes ev-pulse { 0% { box-shadow:0 0 0 0 rgba(255,255,255,.75); } 70% { box-shadow:0 0 0 .65rem rgba(255,255,255,0); } 100% { box-shadow:0 0 0 0 rgba(255,255,255,0); } }
        .ev-heure { font-size:3.2rem; font-weight:800; line-height:1; margin:.4rem 0 .25rem; }
        .ev-jour { font-size:.85rem; opacity:.92; text-transform:capitalize; }
        .ev-compte { display:flex; flex-direction:column; justify-content:center; gap:.2rem; padding:1rem 1.25rem; border-radius:1rem; border:1px solid rgba(128,128,128,.3); }
        .ev-compte-t { font-size:.8rem; opacity:.7; }
        .ev-compte-v { font-size:1.9rem; font-weight:800; }
        .ev-maj { font-size:.72rem; opacity:.55; }
        .ev-lien { align-self:flex-start; margin-top:.35rem; padding:.3rem .75rem; font-size:.8rem; font-weight:600; cursor:pointer; color:inherit; background:transparent; border-radius:999px; border:1px solid rgba(245,158,11,.8); }
        .ev-lien:hover { background:rgba(245,158,11,.15); }
        .ev-filtres .ev-lien { align-self:center; margin-top:0; }
        .ev-titre { font-size:.72rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; opacity:.6; margin-bottom:.4rem; }
        .ev-trajets { display:grid; grid-template-columns:repeat(auto-fill, minmax(240px, 1fr)); gap:.6rem; }
        .ev-trajet { display:flex; flex-direction:column; align-items:flex-start; gap:.1rem; padding:.7rem .9rem; text-align:left; cursor:pointer; color:inherit; background:transparent; border-radius:.8rem; border:1px solid rgba(128,128,128,.35); }
        .ev-trajet:hover { border-color:#f59e0b; }
        .ev-sel { border-color:#f59e0b; background:rgba(245,158,11,.12); box-shadow:0 0 0 1px #f59e0b; }
        .ev-t-titre { font-weight:700; }
        .ev-t-sous { font-size:.8rem; opacity:.7; }
        .ev-t-nb { margin-top:.2rem; font-size:.78rem; font-weight:600; color:#16a34a; }
        .ev-vues { display:flex; flex-wrap:wrap; gap:.5rem; }
        .ev-vue { --c:#6b7280; display:flex; align-items:center; gap:.5rem; padding:.45rem .9rem; cursor:pointer; color:inherit; font-size:.85rem; border-radius:999px; border:1px solid color-mix(in srgb, var(--c) 55%, transparent); background:transparent; }
        .ev-vue:hover { background:color-mix(in srgb, var(--c) 15%, transparent); }
        .ev-vue-on { background:color-mix(in srgb, var(--c) 22%, transparent); box-shadow:0 0 0 1px var(--c); font-weight:600; }
        .ev-vue-n { min-width:1.6rem; padding:.05rem .45rem; text-align:center; font-weight:800; color:#fff; border-radius:999px; background:var(--c); }
        .ev-note { font-size:.8rem; opacity:.7; }
        .ev-strip { display:flex; gap:.5rem; padding-bottom:.35rem; overflow-x:auto; }
        .ev-cren { display:flex; flex:none; flex-direction:column; align-items:center; min-width:74px; padding:.4rem .7rem; cursor:pointer; color:inherit; background:transparent; border-radius:.7rem; border:1px solid rgba(128,128,128,.35); }
        .ev-cren:hover { border-color:#f59e0b; }
        .ev-cren-h { font-weight:700; }
        .ev-cren-n { font-size:.72rem; opacity:.7; }
        .ev-passe { opacity:.45; }
        .ev-actif { opacity:1; color:#fff; border-color:transparent; background:linear-gradient(135deg, #f59e0b, #dc2626); }
    </style>

    @php
        $nbCompagnies = $filtres['compagnies']->count();
        $nbVilles = $filtres['villes']->count();
    @endphp

    {{-- Filtres rapides : visibles seulement s'il y a plusieurs compagnies ou villes à choisir --}}
    @if ($nbCompagnies > 1 || $nbVilles > 1 || $filtreActif)
        <div class="ev-filtres">
            @if ($nbCompagnies > 1)
                <div class="ev-filtre">
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="compagnieId">
                            <option value="">Toutes les compagnies</option>
                            @foreach ($filtres['compagnies'] as $id => $nom)
                                <option value="{{ $id }}">{{ $nom }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            @endif

            @if ($nbVilles > 1)
                <div class="ev-filtre">
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="villeId">
                            <option value="">Toutes les villes</option>
                            @foreach ($filtres['villes'] as $id => $nom)
                                <option value="{{ $id }}">{{ $nom }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            @endif

            @if ($filtreActif)
                <button type="button" class="ev-lien" wire:click="reinitialiserFiltres">Réinitialiser</button>
            @endif
        </div>
    @endif

    @if (! $creneau)
        <div class="ev-vide">
            {{ $filtreActif ? 'Aucun départ pour cette sélection.' : 'Aucun départ programmé dans les prochains jours pour votre périmètre.' }}
        </div>
    @else
        @php
            $multi = $trajets->pluck('compagnie')->unique()->count() > 1;
        @endphp

        <div class="ev-haut">
            <div class="ev-bloc">
                <div class="ev-live">
                    <span class="ev-dot"></span>
                    {{ $manuel ? 'MODE MANUEL' : 'EN DIRECT' }}
                    @if (! $estAujourdhui)
                        · {{ $jour->isTomorrow() ? 'DEMAIN' : 'À VENIR' }}
                    @endif
                </div>
                <div class="ev-heure">{{ $label }}</div>
                <div class="ev-jour">{{ $jour->copy()->locale('fr')->translatedFormat('l d F Y') }}</div>
            </div>

            <div class="ev-compte"
                 wire:key="cd-{{ $jour->toDateString() }}-{{ $creneau }}-{{ $manuel ? 'm' : 'a' }}"
                 x-data="{
                     depart: {{ $departTs }},
                     fin: {{ $finTs }},
                     off: {{ $maintenantTs }} - Date.now() / 1000,
                     auto: {{ $manuel ? 'false' : 'true' }},
                     now: 0,
                     t: null,
                     fait: false,
                     init() {
                         this.tick();
                         this.t = setInterval(() => this.tick(), 1000);
                     },
                     tick() {
                         if (! this.$el.isConnected) { clearInterval(this.t); return; }
                         this.now = Date.now() / 1000 + this.off;
                         if (this.auto && this.now >= this.fin && ! this.fait) {
                             this.fait = true;
                             this.$wire.$refresh();
                         }
                     },
                     fmt(s) {
                         s = Math.max(0, Math.floor(s));
                         const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), r = s % 60;
                         return (h ? h + ' h ' : '') + String(m).padStart(2, '0') + ' min ' + String(r).padStart(2, '0') + ' s';
                     }
                 }">
                <div class="ev-compte-t"
                     x-text="now < depart ? 'Départ dans' : (now < fin ? 'Embarquement final, bascule automatique dans' : 'Départ effectué')"></div>
                <div class="ev-compte-v"
                     x-text="now < depart ? fmt(depart - now) : (now < fin ? fmt(fin - now) : '—')"></div>
                <div class="ev-maj">Actualisé à {{ $maj }} · mise à jour automatique</div>

                @if ($manuel)
                    <button type="button" class="ev-lien" wire:click="suivreAuto">Revenir au suivi automatique</button>
                @endif
            </div>
        </div>

        <div>
            <div class="ev-titre">Trajets au départ de {{ $label }}</div>
            <div class="ev-trajets">
                <button type="button" @class(['ev-trajet', 'ev-sel' => ! $routeChoisie]) wire:key="t-tous-{{ $creneau }}" wire:click="choisirRoute(null)">
                    <span class="ev-t-titre">Tous les trajets</span>
                    <span class="ev-t-sous">{{ $trajets->count() }} trajet(s) à {{ $label }}</span>
                </button>

                @foreach ($trajets as $t)
                    <button type="button" @class(['ev-trajet', 'ev-sel' => $routeChoisie === $t['route_id']]) wire:key="t-{{ $t['route_id'] }}-{{ $creneau }}" wire:click="choisirRoute({{ $t['route_id'] }})">
                        <span class="ev-t-titre">{{ $t['depart_ville'] }} → {{ $t['arrivee_ville'] }}</span>
                        <span class="ev-t-sous">{{ $t['gare'] }}@if ($multi) · {{ $t['compagnie'] }}@endif</span>
                        <span class="ev-t-nb">
                            {{ $t['prets'] }} prêt(s) · {{ $t['embarques'] }} embarqué(s)@if ($t['attente'] > 0) · {{ $t['attente'] }} en attente @endif
                        </span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    <div class="ev-vues">
        @if ($creneau && ! $tousLesBillets)
            @foreach ($vues as $cle => $v)
                <button type="button" @class(['ev-vue', 'ev-vue-on' => $v['active']]) style="--c: {{ $v['couleur'] }}" wire:key="vue-{{ $cle }}" wire:click="choisirVue('{{ $cle }}')">
                    <span class="ev-vue-n">{{ $v['total'] }}</span>
                    <span>{{ $v['label'] }}</span>
                </button>
            @endforeach
        @endif

        <button type="button" @class(['ev-vue', 'ev-vue-on' => $tousLesBillets]) style="--c: #6366f1" wire:key="vue-tous" wire:click="basculerTousLesBillets">
            <span class="ev-vue-n">∞</span>
            <span>{{ $tousLesBillets ? 'Revenir au voyage en cours' : 'Voir tous les billets' }}</span>
        </button>
    </div>

    @if ($tousLesBillets)
        <div class="ev-note">Le tableau affiche tous les billets de votre périmètre, tous jours et tous voyages confondus. Utilisez la recherche pour retrouver une référence.</div>
    @endif

    @if ($creneau)
        <div>
            <div class="ev-titre">Départs du jour (voyageurs prêts)</div>
            <div class="ev-strip"
                 wire:key="strip-{{ $jour->toDateString() }}-{{ $creneau }}"
                 x-data
                 x-init="$nextTick(() => $el.querySelector('.ev-actif')?.scrollIntoView({ inline: 'center', block: 'nearest' }))">
                @foreach ($strip as $s)
                    <button type="button" @class(['ev-cren', 'ev-actif' => $s['actif'], 'ev-passe' => $s['passe'] && ! $s['actif']]) wire:key="cren-{{ $s['cle'] }}" wire:click="choisirCreneau('{{ $s['cle'] }}')">
                        <span class="ev-cren-h">{{ $s['label'] }}</span>
                        <span class="ev-cren-n">{{ $s['prets'] }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif
</div>