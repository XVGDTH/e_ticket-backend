<?php

namespace App\Filament\Resources\Employes\RelationManagers;

use App\Models\ContratEmploye;
use App\Services\Personnel\AccesPersonnel;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ContratsRelationManager extends RelationManager
{
    protected static string $relationship = 'contrats';

    protected static ?string $title = 'Contrats et salaire';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return AccesPersonnel::gerant(auth()->user());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contrat')->columns(3)->schema([
                Select::make('type')->label('Type de contrat')->options(ContratEmploye::TYPES)->required()->live(),
                DatePicker::make('date_debut')->label('Début')->required()->default(today()),
                DatePicker::make('date_fin')->label('Fin')
                    ->required(fn (Get $get) => in_array($get('type'), ['cdd', 'stage', 'journalier', 'apprentissage', 'prestation'], true))
                    ->helperText('Vide pour un CDI.')->afterOrEqual('date_debut'),
                DatePicker::make('fin_essai')->label('Fin de la période d\'essai'),
                TextInput::make('categorie')->label('Catégorie / échelon')->maxLength(80)->placeholder('Ex. Catégorie 3, échelon 1'),
                Toggle::make('cadre')->label('Cadre ou supérieur')->inline(false)
                    ->helperText('Abattement IUTS de '.config('personnel.iuts.abattement_cadre_pct', 20).' % au lieu de '.config('personnel.iuts.abattement_pct', 25).' %.'),
                TextInput::make('heures_hebdo')->label('Heures par semaine')->numeric()->default(40)->minValue(1)->maxValue(80)->required(),
            ]),
            Section::make('Rémunération')->columns(2)->schema([
                Select::make('mode')->label('Mode de rémunération')->options(ContratEmploye::MODES)->default('mensuel')->required()->live(),
                TextInput::make('salaire_base')
                    ->label(fn (Get $get) => $get('mode') === 'journalier' ? 'Taux par jour travaillé' : 'Salaire de base mensuel')
                    ->numeric()->minValue(0)->required()->suffix('FCFA')
                    ->helperText('SMIG indicatif : '.number_format((int) config('personnel.smig_mensuel'), 0, ',', ' ').' FCFA par mois (à confirmer).'),
                Repeater::make('elements_fixes')->label('Indemnités et primes fixes mensuelles')->columnSpanFull()
                    ->addActionLabel('Ajouter une indemnité')->collapsible()->defaultItems(0)->columns(4)
                    ->schema([
                        TextInput::make('libelle')->label('Libellé')->required()->placeholder('Transport, logement, responsabilité…'),
                        TextInput::make('montant')->label('Montant')->numeric()->minValue(0)->required()->suffix('FCFA'),
                        Toggle::make('soumis_cnss')->label('Soumise à la CNSS')->default(true)->inline(false),
                        Toggle::make('soumis_iuts')->label('Imposable (IUTS)')->default(true)->inline(false),
                    ])
                    ->helperText('Décochez « Imposable » ou « CNSS » pour les éléments exonérés selon votre comptable.'),
            ]),
            Section::make('Suivi')->columns(2)->schema([
                FileUpload::make('fichier')->label('Contrat signé (scan)')->directory('personnel/contrats')
                    ->acceptedFileTypes(['application/pdf', 'image/*'])->openable()->downloadable(),
                Toggle::make('actif')->label('Contrat en cours')->default(true)
                    ->helperText('Un seul contrat peut être en cours : activer celui-ci désactive les autres.'),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('date_debut', 'desc')
            ->columns([
                TextColumn::make('type')->label('Contrat')->badge()->formatStateUsing(fn ($s) => ContratEmploye::TYPES[$s] ?? $s)
                    ->description(fn (ContratEmploye $r) => $r->categorie),
                TextColumn::make('date_debut')->label('Du')->date('d/m/Y'),
                TextColumn::make('date_fin')->label('Au')->date('d/m/Y')->placeholder('Sans fin')
                    ->color(fn (ContratEmploye $r) => $r->actif && $r->joursRestants() !== null && $r->joursRestants() <= (int) config('personnel.alerte_jours') ? 'danger' : null),
                TextColumn::make('salaire_base')->label('Salaire de base')->numeric(0, ',', ' ')->suffix(' FCFA')->alignEnd()
                    ->description(fn (ContratEmploye $r) => $r->mode === 'journalier' ? 'par jour travaillé' : 'par mois'),
                TextColumn::make('indemnites')->label('Indemnités fixes')->alignEnd()
                    ->getStateUsing(fn (ContratEmploye $r) => number_format($r->totalElementsFixes(), 0, ',', ' ').' FCFA'),
                IconColumn::make('actif')->label('En cours')->boolean(),
            ])
            ->headerActions([CreateAction::make()->label('Nouveau contrat')])
            ->recordActions([EditAction::make()]);
    }
}
