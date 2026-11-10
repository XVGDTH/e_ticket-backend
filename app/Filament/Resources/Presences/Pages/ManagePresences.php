<?php

namespace App\Filament\Resources\Presences\Pages;

use App\Filament\Resources\Presences\PresenceResource;
use App\Models\Employe;
use App\Models\Presence;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManagePresences extends ManageRecords
{
    protected static string $resource = PresenceResource::class;

    protected function getHeaderActions(): array
    {
        $u = auth()->user();

        return [
            Action::make('feuille')
                ->label('Ouvrir la feuille du jour')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('success')
                ->modalHeading('Ouvrir la feuille de présence')
                ->modalDescription('Tous les employés en poste sont marqués « Présent ». Corrigez ensuite uniquement les exceptions dans la liste. Les congés approuvés sont conservés.')
                ->schema([
                    DatePicker::make('date')->label('Jour')->default(today())->required()
                        ->minDate($u->isAgentGare() ? today()->subDays(7) : null)->maxDate($u->isAgentGare() ? today() : null),
                    Select::make('gare_id')->label('Gare')->options(fn () => Employe::garesOptionsPour(auth()->user()))
                        ->placeholder('Toutes mes gares')->visible(! $u->isAgentGare()),
                ])
                ->action(function (array $data) {
                    $date = Carbon::parse($data['date'])->startOfDay();

                    $employes = Employe::query()->visiblePour(auth()->user())
                        ->where('statut', 'actif')->whereDate('date_embauche', '<=', $date->toDateString())
                        ->when($data['gare_id'] ?? null, fn ($q, $g) => $q->where('gare_id', $g))->get();

                    $crees = 0;

                    foreach ($employes as $e) {
                        $p = Presence::firstOrCreate(
                            ['employe_id' => $e->id, 'date' => $date->toDateString()],
                            ['statut' => 'present']
                        );

                        $crees += $p->wasRecentlyCreated ? 1 : 0;
                    }

                    Notification::make()->title($crees.' employé(s) pointé(s) présents')
                        ->body($employes->count() - $crees.' ligne(s) existaient déjà.')->success()->send();
                }),
            CreateAction::make()->label('Pointer un employé'),
        ];
    }
}
