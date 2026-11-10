<?php

namespace App\Filament\Resources\ClientsFideles\Pages;

use App\Filament\Resources\ClientsFideles\PreferenceClientResource;
use App\Services\Marketing\Audience;
use App\Services\Marketing\Preferences;
use App\Services\Marketing\Telephone;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListClientsFideles extends ListRecords
{
    protected static string $resource = PreferenceClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recalculer')
                ->label('Recalculer les préférences')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function () {
                    abort_unless(auth()->user()->isSuperAdmin(), 403);

                    $r = Preferences::recalculer();

                    Notification::make()->title('Préférences recalculées')
                        ->body($r['segments']['fidele'].' fidèle(s), '.$r['segments']['regulier'].' régulier(s).')
                        ->success()->send();
                }),
            Action::make('exporter')
                ->label('Exporter en CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    abort_unless(auth()->user()->isSuperAdmin(), 403);

                    $requete = $this->getFilteredSortedTableQuery()->with(['user', 'compagnie', 'gare']);

                    return response()->streamDownload(function () use ($requete) {
                        $h = fopen('php://output', 'w');
                        fwrite($h, "\xEF\xBB\xBF");
                        fputcsv($h, ['Prénom', 'Nom', 'Téléphone WhatsApp', 'Compagnie', 'Gare', 'Score', 'Type', 'Billets', 'Dernier achat', 'Accepte les offres'], ';', '"', '\\');

                        foreach ($requete->lazy(500) as $r) {
                            fputcsv($h, [
                                $r->user?->prenom, $r->user?->nom, Telephone::whatsapp($r->user?->telephone),
                                $r->compagnie?->nom, $r->gare?->quartier ?? 'Toutes', $r->score,
                                Audience::SEGMENTS[$r->segment] ?? $r->segment, $r->nb_billets,
                                $r->dernier_achat?->format('d/m/Y'), $r->user?->marketing_whatsapp ? 'oui' : 'non',
                            ], ';', '"', '\\');
                        }

                        fclose($h);
                    }, 'clients-fideles-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
        ];
    }
}
