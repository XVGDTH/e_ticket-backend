<?php

namespace App\Filament\Resources\Employes\RelationManagers;

use App\Models\DocumentEmploye;
use App\Services\Personnel\AccesPersonnel;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Pièces du dossier';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return AccesPersonnel::gerant(auth()->user());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')->label('Type de pièce')->options(DocumentEmploye::TYPES)->required(),
            TextInput::make('libelle')->label('Précision')->maxLength(120)->placeholder('Ex. Licence en gestion'),
            DatePicker::make('expire_le')->label('Date d\'expiration')->helperText('Pour être alerté avant l\'échéance (CNIB, permis, visite médicale…).'),
            FileUpload::make('fichier')->label('Fichier (PDF ou photo)')->required()->directory('personnel/documents')
                ->acceptedFileTypes(['application/pdf', 'image/*'])->maxSize(8192)->openable()->downloadable()->columnSpanFull(),
        ])->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')->label('Pièce')->badge()->formatStateUsing(fn ($s) => DocumentEmploye::TYPES[$s] ?? $s)
                    ->description(fn (DocumentEmploye $r) => $r->libelle),
                TextColumn::make('expire_le')->label('Expire le')->date('d/m/Y')->placeholder('—')
                    ->color(fn (DocumentEmploye $r) => match (true) {
                        $r->jours() === null => null,
                        $r->jours() < 0 => 'danger',
                        $r->jours() <= (int) config('personnel.alerte_jours') => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('created_at')->label('Ajoutée le')->date('d/m/Y'),
            ])
            ->headerActions([CreateAction::make()->label('Ajouter une pièce')])
            ->recordActions([
                Action::make('ouvrir')->label('Ouvrir')->icon('heroicon-o-eye')
                    ->url(fn (DocumentEmploye $r) => Storage::url($r->fichier))->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
