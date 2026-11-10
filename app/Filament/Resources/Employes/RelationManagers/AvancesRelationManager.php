<?php

namespace App\Filament\Resources\Employes\RelationManagers;

use App\Models\AvanceEmploye;
use App\Services\Personnel\AccesPersonnel;
use App\Services\Personnel\CalculPaie;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AvancesRelationManager extends RelationManager
{
    protected static string $relationship = 'avances';

    protected static ?string $title = 'Avances et prêts';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return AccesPersonnel::gerant(auth()->user());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')->label('Nature')->options(AvanceEmploye::TYPES)->default('avance')->required(),
            TextInput::make('montant')->label('Montant accordé')->numeric()->minValue(1000)->required()->suffix('FCFA'),
            TextInput::make('nb_mensualites')->label('Nombre de mensualités')->numeric()->minValue(1)->maxValue(24)->default(1)->required()
                ->rules([
                    fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                        $contrat = $this->getOwnerRecord()->contratActif;
                        $montant = (int) $get('montant');

                        if (! $contrat || (int) $contrat->salaire_base <= 0 || $montant <= 0 || (int) $value <= 0) {
                            return;
                        }

                        $mensualite = (int) ceil($montant / (int) $value);
                        $quotite = CalculPaie::quotiteCessibleContrat($this->getOwnerRecord(), $contrat);

                        if ($mensualite > $quotite) {
                            $fail('La mensualité ('.number_format($mensualite, 0, ',', ' ').' FCFA) dépasse la quotité cessible estimée de l\'employé ('.number_format($quotite, 0, ',', ' ').' FCFA par mois) : augmentez le nombre de mensualités.');
                        }
                    },
                ]),
            DatePicker::make('date_octroi')->label('Date de remise')->default(today())->required(),
            DatePicker::make('debut_remboursement')->label('Début des retenues')->helperText('Vide = le mois suivant.'),
            TextInput::make('motif')->label('Motif')->maxLength(255),
        ])->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('date_octroi', 'desc')
            ->columns([
                TextColumn::make('type')->label('Nature')->badge()->formatStateUsing(fn ($s) => AvanceEmploye::TYPES[$s] ?? $s)
                    ->description(fn (AvanceEmploye $r) => $r->motif),
                TextColumn::make('date_octroi')->label('Remise le')->date('d/m/Y'),
                TextColumn::make('montant')->label('Montant')->numeric(0, ',', ' ')->suffix(' FCFA')->alignEnd(),
                TextColumn::make('mensualite')->label('Mensualité')->numeric(0, ',', ' ')->suffix(' FCFA')->alignEnd()
                    ->description(fn (AvanceEmploye $r) => $r->nb_mensualites.' mensualité(s)'),
                TextColumn::make('reste_du')->label('Reste dû')->numeric(0, ',', ' ')->suffix(' FCFA')->alignEnd()->weight('bold'),
                TextColumn::make('statut')->label('État')->badge()->formatStateUsing(fn ($s) => AvanceEmploye::STATUTS[$s] ?? $s)
                    ->color(fn ($s) => match ($s) {
                        'en_cours' => 'warning', 'solde' => 'success', default => 'gray'
                    }),
            ])
            ->headerActions([CreateAction::make()->label('Accorder une avance')])
            ->recordActions([
                Action::make('annuler')->label('Annuler')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (AvanceEmploye $r) => $r->statut === 'en_cours' && $r->reste_du === $r->montant)
                    ->requiresConfirmation()->modalDescription('Aucun remboursement n\'a encore été retenu : l\'avance sera simplement annulée.')
                    ->action(function (AvanceEmploye $record) {
                        $record->forceFill(['statut' => 'annule', 'reste_du' => 0])->save();
                        Notification::make()->title('Avance annulée')->success()->send();
                    }),
            ]);
    }
}
