<?php

namespace App\Filament\Resources\PeriodesPaie\RelationManagers;

use App\Models\BulletinPaie;
use App\Services\Personnel\AccesPersonnel;
use App\Services\Personnel\CalculPaie;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Route;

class BulletinsRelationManager extends RelationManager
{
    protected static string $relationship = 'bulletins';

    protected static ?string $title = 'Bulletins de paie';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('primes_variables')->label('Primes variables du mois')->numeric()->minValue(0)->default(0)->suffix('FCFA')
                ->helperText('Prime de rendement, de voyage… soumise à la CNSS et à l\'IUTS.'),
            TextInput::make('autres_retenues')->label('Autres retenues')->numeric()->minValue(0)->default(0)->suffix('FCFA')
                ->helperText('Casse, manquant de caisse, acompte déjà versé…'),
            TextInput::make('observation')->label('Observation')->maxLength(255)->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        $fcfa = fn (TextColumn $c) => $c->numeric(0, ',', ' ')->alignEnd();

        return $table
            ->modifyQueryUsing(fn ($query) => $query->visiblePour(auth()->user())->with('employe'))
            ->columns([
                Stack::make([
                    TextColumn::make('employe.nom')->label('Employé')->weight('bold')
                        ->formatStateUsing(fn ($state, BulletinPaie $r) => $r->employe->nomComplet())
                        ->description(fn (BulletinPaie $r) => $r->employe->matricule.' · '.$r->employe->poste)
                        ->searchable(['nom', 'prenoms']),
                    $fcfa(TextColumn::make('net_a_payer')->label('Net à payer')->weight('bold')->size('lg')
                        ->description(fn (BulletinPaie $r) => ($r->detail['alertes'] ?? []) ? '⚠ '.$r->detail['alertes'][0] : 'Montant net du bulletin')),
                    $fcfa(TextColumn::make('brut')->label('Brut')),
                    Split::make([
                        $fcfa(TextColumn::make('cnss_salarie')->label('CNSS')),
                        $fcfa(TextColumn::make('iuts')->label('IUTS')),
                        $fcfa(TextColumn::make('retenue_avance')->label('Avances')),
                    ])->from('sm'),
                    TextColumn::make('statut_paiement')->label('Paiement')->badge()
                        ->formatStateUsing(fn ($s) => $s === 'paye' ? 'Payé' : 'À payer')->color(fn ($s) => $s === 'paye' ? 'success' : 'warning'),
                ])->space(3),
            ])
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->recordActions([
                EditAction::make()->label('Primes / retenues')
                    ->visible(fn (BulletinPaie $r) => AccesPersonnel::paieGestion(auth()->user()) && $r->periode->statut === 'brouillon')
                    ->after(function (BulletinPaie $record) {
                        try {
                            CalculPaie::recalculer($record->refresh());
                        } catch (\DomainException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('imprimer')->label('Bulletin')->icon('heroicon-o-printer')->color('gray')
                    ->visible(fn () => Route::has('personnel.bulletin'))
                    ->url(fn (BulletinPaie $r) => route('personnel.bulletin', $r->id))->openUrlInNewTab(),
                Action::make('payer')->label('Payé')->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (BulletinPaie $r) => AccesPersonnel::paieGestion(auth()->user()) && $r->periode->statut !== 'brouillon' && $r->statut_paiement !== 'paye')
                    ->schema([TextInput::make('reference')->label('Référence du paiement (facultatif)')->placeholder('N° de transaction, reçu…')])
                    ->action(function (BulletinPaie $record, array $data) {
                        try {
                            CalculPaie::payerBulletin($record, $data['reference'] ?? null);
                            Notification::make()->title('Paiement enregistré')->success()->send();
                        } catch (\DomainException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->striped();
    }
}
