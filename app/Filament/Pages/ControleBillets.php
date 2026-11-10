<?php

namespace App\Filament\Pages;

use App\Enums\BilletStatut;
use App\Models\Billet;
use App\Models\VerificationBillet;
use App\Services\BilletToken;
use App\Services\ControleBillet;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\RateLimiter;
use UnitEnum;

class ControleBillets extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.controle-billets';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Contrôle des billets';

    protected static string|UnitEnum|null $navigationGroup = 'Transport';

    protected static ?int $navigationSort = 1;

    public string $saisie = '';

    /** Résultat du dernier contrôle (tableau simple, sans objets). */
    public ?array $resultat = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->isSuperAdmin() || $user->isAdminCompagnie() || $user->isAgentGare());
    }

    public function getTitle(): string
    {
        return 'Contrôle des billets';
    }

    public function verifier(string $contenu): void
    {
        abort_unless(static::canAccess(), 403);

        $user = auth()->user();
        $cle = 'controle-billet:'.$user->id;

        if (RateLimiter::tooManyAttempts($cle, 90)) {
            Notification::make()->title('Trop de contrôles à la suite')->body('Patientez une minute.')->warning()->send();

            return;
        }

        RateLimiter::hit($cle, 60);

        $this->resultat = ControleBillet::analyser($contenu, $user);
        $this->saisie = '';

        $this->dispatch('billet-verifie', niveau: $this->resultat['niveau']);
    }

    public function verifierSaisie(): void
    {
        $this->verifier($this->saisie);
    }

    public function embarquer(int $billetId): void
    {
        $user = auth()->user();

        abort_unless($user->isAgentGare(), 403);

        $billet = Billet::with('route')->findOrFail($billetId);

        abort_unless((int) $billet->route?->gare_depart_id === (int) $user->gareId(), 403);

        try {
            $billet->changerStatut(BilletStatut::Utilise, $user, null, 'panel');

            Notification::make()->title('Voyageur embarqué')->success()->send();
        } catch (AuthorizationException|\DomainException $e) {
            Notification::make()->title('Embarquement refusé')->body($e->getMessage())->danger()->send();
        }

        // On recontrôle sans le compter comme un nouveau scan.
        $this->resultat = ControleBillet::analyser(BilletToken::generer($billet->fresh()), $user, false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => VerificationBillet::query()->visiblePour(auth()->user())->with(['billet', 'agent', 'gare']))
            ->heading('Derniers contrôles')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Heure')->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('resultat')
                    ->label('Résultat')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ControleBillet::LIBELLES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'valide' => 'success',
                        'a_reporter', 'trop_tot' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('billet.reference')->label('Billet')->placeholder('—')->fontFamily('mono'),
                TextColumn::make('mode')
                    ->label('Lecture')
                    ->formatStateUsing(fn ($state) => $state === 'qr' ? 'Code QR' : 'Saisie manuelle'),
                TextColumn::make('agent')
                    ->label('Contrôleur')
                    ->getStateUsing(fn (VerificationBillet $r) => trim(($r->agent?->prenom ?? '').' '.($r->agent?->nom ?? '')) ?: '—'),
                TextColumn::make('gare.quartier')->label('Gare')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('resultat')->label('Résultat')->options(ControleBillet::LIBELLES),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->striped();
    }
}
