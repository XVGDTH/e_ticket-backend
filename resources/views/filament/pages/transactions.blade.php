<x-filament-panels::page>
    @include('filament.partials.finance-styles')

    @php
        $t = $this->totauxFiltres();
        $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
        $tuiles = [
            ['Total payé', $t->prix + $t->frais, ' FCFA', 'heroicon-o-banknotes'],
            ['Prix des billets', $t->prix, ' FCFA', 'heroicon-o-ticket'],
            ['Frais', $t->frais, ' FCFA', 'heroicon-o-receipt-percent'],
            ['Transactions', $t->billets, '', 'heroicon-o-qr-code'],
            ['Voyageurs', $t->voyageurs, '', 'heroicon-o-users'],
            ['Acheteurs', $t->clients, '', 'heroicon-o-user-group'],
        ];
    @endphp

    <div class="rf">
        <div class="rf-hero" style="padding:22px 28px">
            <div class="rf-hero-grid" style="grid-template-columns:1fr">
                <div>
                    <div class="rf-eyebrow">Résultat des filtres en cours</div>
                    <div class="rf-big"
                         wire:key="tx-{{ (int) ($t->prix + $t->frais) }}"
                         x-data="{ v: 0, cible: {{ (int) ($t->prix + $t->frais) }} }"
                         x-init="const s = performance.now(); const tick = (now) => { const k = Math.min(1, (now - s) / 900); v = Math.round(cible * (1 - Math.pow(1 - k, 3))); if (k < 1) requestAnimationFrame(tick); }; requestAnimationFrame(tick)">
                        <span x-text="v.toLocaleString('fr-FR')">{{ $fmt($t->prix + $t->frais) }}</span><small>FCFA payés</small>
                    </div>
                    <div class="rf-chips">
                        <span class="rf-chip">{{ $fmt($t->billets) }} transaction(s)</span>
                        <span class="rf-chip">dont {{ $fmt($t->frais) }} FCFA de frais</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="rf-grid">
            @foreach ($tuiles as [$lib, $val, $suffixe, $icone])
                <div class="rf-kpi">
                    <div class="rf-ico"><x-filament::icon :icon="$icone" style="width:22px;height:22px" /></div>
                    <div class="rf-kpi-l">{{ $lib }}</div>
                    <div class="rf-kpi-v">{{ $fmt($val) }}<small>{{ $suffixe }}</small></div>
                </div>
            @endforeach
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>