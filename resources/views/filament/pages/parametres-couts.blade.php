<x-filament-panels::page>
    <div class="pc">
        <style>
            .pc { display:flex; flex-direction:column; gap:1.1rem; width:100%; min-width:0; box-sizing:border-box; }
            .pc-grille { display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:.9rem; }
            .pc-carte { --c:#0ea5e9; display:flex; flex-direction:column; gap:.6rem; padding:1rem 1.2rem; border-radius:1rem; border:1px solid rgba(128,128,128,.3); border-top:4px solid var(--c); }
            .pc-t { font-weight:700; }
            .pc-s { font-size:.8rem; opacity:.7; line-height:1.4; }
            .pc-err { font-size:.78rem; color:#dc2626; }
            .pc-filtre { max-width:260px; }
        </style>

        @if ($compagnies->count() > 1)
            <div class="pc-filtre">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="compagnieId">
                        @foreach ($compagnies as $id => $nom)
                            <option value="{{ $id }}">{{ $nom }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        @endif

        <div class="pc-grille">
            <div class="pc-carte" style="--c: #f59e0b">
                <div class="pc-t">Carburant standard</div>
                <div class="pc-s">Coût standard d’un départ = consommation × kilomètres ÷ 100 × prix du litre. Il sert tant que le plein réel n’est pas saisi, et à mesurer l’écart.</div>
                <x-filament::input.wrapper suffix="L/100 km">
                    <x-filament::input type="number" step="any" wire:model="norme_l100" />
                </x-filament::input.wrapper>
                @error('norme_l100') <div class="pc-err">{{ $message }}</div> @enderror
                <x-filament::input.wrapper suffix="FCFA / L">
                    <x-filament::input type="number" step="1" wire:model="prix_litre" />
                </x-filament::input.wrapper>
                @error('prix_litre') <div class="pc-err">{{ $message }}</div> @enderror
            </div>

            <div class="pc-carte" style="--c: #dc2626">
                <div class="pc-t">Seuil d’écart</div>
                <div class="pc-s">Au-delà de ce pourcentage, l’écart est signalé comme anormal et exige le commentaire du responsable.</div>
                <x-filament::input.wrapper suffix="%">
                    <x-filament::input type="number" step="any" wire:model="ecart_seuil_pct" />
                </x-filament::input.wrapper>
                @error('ecart_seuil_pct') <div class="pc-err">{{ $message }}</div> @enderror
            </div>

            <div class="pc-carte" style="--c: #6366f1">
                <div class="pc-t">Commission de paiement</div>
                <div class="pc-s">Part des ventes de l’application prélevée par l’agrégateur de paiement. Laissez 0 si aucune commission n’est à votre charge.</div>
                <x-filament::input.wrapper suffix="%">
                    <x-filament::input type="number" step="any" wire:model="commission_paiement_pct" />
                </x-filament::input.wrapper>
                @error('commission_paiement_pct') <div class="pc-err">{{ $message }}</div> @enderror
            </div>

            <div class="pc-carte" style="--c: #22c55e">
                <div class="pc-t">Équipage</div>
                <div class="pc-s">Le coût de l’équipage vient de la paie : coût employeur du dernier bulletin du chauffeur, divisé par ses heures mensuelles (heures hebdomadaires de son contrat × 52 ÷ 12, soit 40 h par défaut), multiplié par la durée de la ligne. Rien à régler ici.</div>
            </div>
        </div>

        <div>
            <x-filament::button wire:click="enregistrer" icon="heroicon-o-check">Enregistrer</x-filament::button>
        </div>
    </div>
</x-filament-panels::page>