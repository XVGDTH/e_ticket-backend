<x-filament-panels::page>
    @include('filament.partials.finance-styles')
    @include('filament.partials.modules-styles')

    @php $d = $this->donnees(); @endphp

    <div class="rf">
        @if (auth()->user()->isSuperAdmin())
            <div class="rf-bar">
                <select wire:model.live="compagnieId" class="rf-select">
                    @foreach ($d['compagnies'] as $id => $nom)
                        <option value="{{ $id }}">{{ $nom }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if (empty($d['modules']))
            <div class="rf-note">
                Aucun module n'est activé pour cette compagnie. Contactez l'administrateur de la plateforme pour activer les modules dont vous avez besoin.
            </div>
        @else
            <div class="rf-card">
                <h3>Qui utilise quoi ?</h3>
                <div class="sub">Activez, pour chaque gare, les modules que son personnel doit utiliser. Une gare sans module ne voit pas le menu correspondant.</div>

                <div class="rf-scroll" style="max-height:none">
                    <table class="rf-table md-matrice">
                        <thead>
                            <tr>
                                <th>Gare</th>
                                @foreach ($d['modules'] as $cle => $m)
                                    <th class="n" style="--c: {{ $m['couleur'] }}">
                                        <div style="color: {{ $m['couleur'] }}">{{ $m['nom'] }}</div>
                                        <button type="button" class="md-tout" wire:click="toutPourModule('{{ $cle }}', true)">toutes</button>
                                        <button type="button" class="md-tout" wire:click="toutPourModule('{{ $cle }}', false)">aucune</button>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($d['gares'] as $gare)
                                <tr>
                                    <td>
                                        <strong>{{ $gare->quartier }}</strong>
                                        <div class="sub" style="margin:0">{{ $gare->ville?->nom }}</div>
                                    </td>
                                    @foreach ($d['modules'] as $cle => $m)
                                        @php $on = in_array($cle, $d['affectations'][$gare->id] ?? [], true); @endphp
                                        <td class="n" style="--c: {{ $m['couleur'] }}">
                                            <button type="button" class="md-switch {{ $on ? 'on' : '' }}"
                                                    wire:click="basculer({{ $gare->id }}, '{{ $cle }}')" wire:loading.attr="disabled"
                                                    aria-label="{{ $m['nom'] }} pour {{ $gare->quartier }}"><i></i></button>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>