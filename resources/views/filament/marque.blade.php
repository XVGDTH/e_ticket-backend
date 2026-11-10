@php
    $utilisateur = auth()->user();
    $profil = $utilisateur?->profilStaff() ?? [];
    $compagnie = $utilisateur?->compagnieActuelle();
    $logo = $compagnie?->logo_affichable;
    // L'agent voit sa gare ; l'admin rattaché à une ville voit sa ville.
    $lieu = $profil['gare'] ?? $profil['ville'] ?? null;
@endphp

<div style="display:flex; align-items:center; gap:.6rem;">
    @if ($logo)
        <img src="{{ $logo }}" alt="{{ $compagnie->nom }}" style="height:2.5rem; width:auto; max-width:5rem; object-fit:contain;">
    @endif

    <div style="display:flex; flex-direction:column; line-height:1.15;">
        <span style="font-weight:700;">{{ $compagnie?->nom ?? 'E-Ticket Admin' }}</span>
        @if ($lieu)
            <span style="font-size:.75rem; opacity:.7;">{{ $lieu }}</span>
        @endif
    </div>
</div>