<div style="display:flex; flex-direction:column; gap:.6rem;">
    @forelse ($billets as $billet)
        <div style="border:1px solid rgba(128,128,128,.3); border-left:4px solid {{ $couleur }}; border-radius:.6rem; padding:.65rem .85rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:.75rem; flex-wrap:wrap;">
                <strong>{{ $billet->reference }}</strong>
                <x-filament::badge :color="$billet->statut->getColor()">{{ $billet->statut->getLabel() }}</x-filament::badge>
            </div>

            <div style="margin-top:.2rem; font-size:.85rem; opacity:.85;">
                {{ trim(($billet->user?->prenom ?? '') . ' ' . ($billet->user?->nom ?? '')) ?: 'Client' }}
                · {{ $billet->travelers_count }} voyageur(s)
                · {{ number_format((int) $billet->montant_transport, 0, ',', ' ') }} FCFA
            </div>

            <div style="margin-top:.15rem; font-size:.8rem; opacity:.7;">
                {{ $billet->date_voyage->format('d/m/Y') }} à {{ str_replace(':', 'h', substr((string) $billet->heure, 0, 5)) }}
                · {{ $billet->gare?->quartier }} → {{ $villes[$billet->route?->ville_arrivee_id] ?? '—' }}
            </div>

            @php
                $noms = collect($billet->voyageurs ?? [])->pluck('nom')->filter()->implode(', ');
            @endphp
            @if ($noms)
                <div style="margin-top:.15rem; font-size:.8rem; opacity:.7;">{{ $noms }}</div>
            @endif
        </div>
    @empty
        <p style="opacity:.7;">Aucun billet.</p>
    @endforelse

    @if ($total > $billets->count())
        <p style="font-size:.8rem; opacity:.7;">Affichage limité aux {{ $billets->count() }} premiers billets sur {{ $total }}.</p>
    @endif
</div>

