<?php

namespace App\Filament\Resources\DemandesAssistance\Pages;

use App\Filament\Resources\DemandesAssistance\DemandeAssistanceResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Str;

class ViewDemandeAssistance extends ViewRecord
{
    protected static string $resource = DemandeAssistanceResource::class;

    protected function peutRepondre(): bool
    {
        return $this->getRecord()->peutRepondre(auth()->user());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('repondre')
                ->label('Répondre')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->visible(fn () => $this->peutRepondre() && $this->getRecord()->statut !== 'resolue')
                ->modalHeading('Répondre au client')
                ->modalSubmitActionLabel('Envoyer')
                ->schema([
                    Textarea::make('corps')->label('Votre réponse')->required()->minLength(2)->maxLength(2000)->rows(5),
                ])
                ->action(function (array $data): void {
                    $demande = $this->getRecord();
                    abort_unless($demande->peutRepondre(auth()->user()), 403);

                    $message = $demande->ajouterMessage(auth()->user(), $data['corps'], 'staff');
                    $demande->notifierClient(
                        'Réponse à votre demande',
                        Str::limit($data['corps'], 140),
                        'assistance:'.$demande->id.':'.$message->id
                    );

                    $demande->refresh();
                    Notification::make()->title('Réponse envoyée')->success()->send();
                }),

            Action::make('prendre')
                ->label('Prendre en charge')
                ->icon('heroicon-o-hand-raised')
                ->color('gray')
                ->visible(fn () => $this->peutRepondre()
                    && $this->getRecord()->statut !== 'resolue'
                    && $this->getRecord()->assigne_a !== auth()->id())
                ->action(function (): void {
                    $demande = $this->getRecord();
                    abort_unless($demande->peutRepondre(auth()->user()), 403);

                    $demande->forceFill([
                        'assigne_a' => auth()->id(),
                        'statut' => $demande->statut === 'nouvelle' ? 'en_cours' : $demande->statut,
                    ])->save();

                    Notification::make()->title('Demande prise en charge')->success()->send();
                }),

            Action::make('rappel_fait')
                ->label('Rappel effectué')
                ->icon('heroicon-o-phone')
                ->color('success')
                ->visible(fn () => $this->peutRepondre() && $this->getRecord()->rappel_statut === 'demande')
                ->requiresConfirmation()
                ->action(function (): void {
                    $demande = $this->getRecord();
                    abort_unless($demande->peutRepondre(auth()->user()), 403);

                    $demande->forceFill(['rappel_statut' => 'fait', 'derniere_activite_le' => now()])->save();

                    Notification::make()->title('Rappel noté')->success()->send();
                }),

            Action::make('resoudre')
                ->label('Marquer comme résolue')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->peutRepondre() && $this->getRecord()->statut !== 'resolue')
                ->requiresConfirmation()
                ->modalDescription('Le client sera prévenu et pourra noter votre réponse. Il peut rouvrir la demande en écrivant à nouveau.')
                ->action(function (): void {
                    $demande = $this->getRecord();
                    abort_unless($demande->peutRepondre(auth()->user()), 403);

                    $demande->forceFill([
                        'statut' => 'resolue',
                        'resolue_le' => now(),
                        'derniere_activite_le' => now(),
                    ])->save();

                    $demande->notifierClient(
                        'Demande résolue',
                        'Votre demande « '.Str::limit($demande->objet, 60).' » est résolue. Dites-nous si vous êtes satisfait.',
                        'assistance:'.$demande->id.':resolue:'.now()->timestamp
                    );

                    Notification::make()->title('Demande résolue')->success()->send();
                }),
        ];
    }
}
