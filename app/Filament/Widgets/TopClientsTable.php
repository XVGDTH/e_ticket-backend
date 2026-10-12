<?php

namespace App\Filament\Widgets;

use App\Models\Compagnie;
use App\Models\Gare;
use App\Models\User;
use App\Services\Analytics\VentesAnalytics;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopClientsTable extends BaseWidget
{
    protected static ?int $sort = 12;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return VentesAnalytics::role(auth()->user()) !== null;
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $role = VentesAnalytics::role($user);
        $analytics = VentesAnalytics::pour($user);

        $filtres = [];

        if ($role === 'super_admin') {
            $filtres[] = SelectFilter::make('compagnie')
                ->label('Compagnie')
                ->options(Compagnie::orderBy('nom')->pluck('nom', 'id'))
                ->query(fn ($query) => $query);
        }

        if (in_array($role, ['super_admin', 'admin_compagnie'], true)) {
            $gares = ($role === 'super_admin' ? Gare::query() : $user->garesGerables())
                ->with('compagnie')
                ->orderBy('quartier')
                ->get()
                ->mapWithKeys(fn ($g) => [
                    $g->id => $role === 'super_admin' ? "{$g->quartier} ({$g->compagnie?->nom})" : $g->quartier,
                ]);

            $filtres[] = SelectFilter::make('gare')
                ->label('Gare')
                ->options($gares)
                ->query(fn ($query) => $query);
        }

        return $table
            ->heading('Top 30 des clients')
            ->query(function () use ($analytics) {
                // Les filtres sont lus ici : ils servent à restreindre les agrégats, pas la liste des utilisateurs.
                $compagnieId = (int) data_get($this->tableFilters, 'compagnie.value') ?: null;
                $gareId = (int) data_get($this->tableFilters, 'gare.value') ?: null;
                $filtre = $analytics->filtreBillets($compagnieId, $gareId);

                return User::query()
                    ->whereHas('billets', $filtre)
                    ->withCount(['billets as nb_billets' => $filtre])
                    ->withSum(['billets as ca_total' => $filtre], 'montant_transport')
                    ->withMax(['billets as dernier_voyage' => $filtre], 'date_voyage')
                    ->orderByDesc('ca_total')
                    ->limit(30);
            })
            ->columns([
                TextColumn::make('prenom')
                    ->label('Client')
                    ->formatStateUsing(fn ($state, User $record) => trim("{$record->prenom} {$record->nom}")),
                TextColumn::make('telephone')
                    ->label('Téléphone')
                    ->formatStateUsing(fn ($state) => $role === 'agent_gare'
                        ? substr((string) $state, 0, 2) . '••••' . substr((string) $state, -2)
                        : $state),
                TextColumn::make('nb_billets')->label('Billets'),
                TextColumn::make('ca_total')
                    ->label('CA (FCFA)')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 0, ',', ' ')),
                TextColumn::make('dernier_voyage')->label('Dernier voyage')->date('d/m/Y'),
            ])
            ->filters($filtres)
            ->paginated(false);
    }
}