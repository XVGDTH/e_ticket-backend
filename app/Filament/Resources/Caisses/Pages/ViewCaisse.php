<?php

namespace App\Filament\Resources\Caisses\Pages;

use App\Filament\Resources\Caisses\CaisseResource;
use App\Services\Guichet\VenteGuichet;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewCaisse extends ViewRecord
{
    protected static string $resource = CaisseResource::class;

    public function infolist(Schema $schema): Schema
    {
        $f = fn ($v) => number_format((int) $v, 0, ',', ' ').' FCFA';

        return $schema->components([
            Section::make('Caisse')->columns(4)->schema([
                TextEntry::make('agent')->label('Agent')->state(fn ($record) => $record->libelleAgent()),
                TextEntry::make('gare')->label('Gare')->state(fn ($record) => $record->gare?->quartier),
                TextEntry::make('ouverte_le')->label('Ouverte le')->dateTime('d/m/Y H:i'),
                TextEntry::make('fermee_le')->label('Fermée le')->dateTime('d/m/Y H:i')->placeholder('En cours'),
                TextEntry::make('fond')->label('Fond de caisse')->state(fn ($record) => $f($record->fond_initial)),
                TextEntry::make('attendu')->label('Espèces attendues')->state(fn ($record) => $f($record->statut === 'ouverte' ? $record->especesAttendues() : $record->especes_attendues)),
                TextEntry::make('compte')->label('Espèces comptées')->state(fn ($record) => $record->especes_comptees === null ? '—' : $f($record->especes_comptees)),
                TextEntry::make('ecart')->label('Écart')->state(fn ($record) => $record->ecart === null ? '—' : $f($record->ecart))
                    ->color(fn ($record) => $record->ecart === null ? null : ($record->ecart == 0 ? 'success' : 'danger'))->weight('bold'),
            ]),
            Section::make('Encaissé par mode de paiement')->columns(4)->schema(
                collect(config('guichet.modes'))->map(fn ($libelle, $mode) => TextEntry::make('mode_'.$mode)->label($libelle)
                    ->state(fn ($record) => $f($record->totauxParMode()[$mode] ?? 0)))->values()->all()
            ),
            Section::make('Note')->visible(fn ($record) => filled($record->note))->schema([
                TextEntry::make('note')->hiddenLabel(),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('mouvement')->label('Entrée / sortie d\'espèces')->icon('heroicon-o-arrows-right-left')
                ->visible(fn () => $this->record->statut === 'ouverte')
                ->schema([
                    Select::make('sens')->label('Nature')->options(['sortie' => 'Sortie (versement, dépense)', 'entree' => 'Entrée (apport de monnaie)'])->required(),
                    TextInput::make('montant')->label('Montant')->numeric()->minValue(1)->required()->suffix('FCFA'),
                    TextInput::make('motif')->label('Motif')->required()->minLength(3)->maxLength(255),
                ])
                ->action(function (array $data) {
                    try {
                        VenteGuichet::mouvementManuel($this->record, auth()->user(), $data['sens'], (int) $data['montant'], $data['motif']);
                        Notification::make()->title('Mouvement enregistré')->success()->send();
                    } catch (\DomainException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
