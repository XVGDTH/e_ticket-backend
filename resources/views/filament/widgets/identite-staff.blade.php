<x-filament-widgets::widget>
    <x-filament::section>
        <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $compagnie }}" style="height:4rem; width:auto; max-width:9rem; object-fit:contain;">
            @endif

            <div>
                <div style="font-size:1.15rem; font-weight:700;">{{ $nom }}</div>
                <div style="opacity:.75;">{{ $role }}@if ($compagnie) · {{ $compagnie }}@endif</div>

                @if ($gare || $ville)
                    <div style="opacity:.75;">
                        @if ($gare) Gare : {{ $gare }} @endif
                        @if ($gare && $ville) · @endif
                        @if ($ville) Ville : {{ $ville }} @endif
                    </div>
                @endif
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>