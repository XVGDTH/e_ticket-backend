<x-filament-panels::page>
    <x-filament::section>
        <p>
            <strong>Places du car</strong> : le nombre réel de places assis pour chaque compagnie de transport et chaque gare.<br>
            <strong>Quota</strong> :le plafonds attribué par chaque compagnie et chaque gare<br>
            Quand un départ atteint sa limite, il n'est plus proposé aux clients de l'application.
        </p>
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
