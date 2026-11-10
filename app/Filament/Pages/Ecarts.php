<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\ChoisitCompagnie;
use App\Models\CgCloture;
use App\Models\CgCommentaire;
use App\Models\Compagnie;
use App\Services\ControleGestion\Acces;
use App\Services\ControleGestion\Reporting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class Ecarts extends Page
{
    use ChoisitCompagnie;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Écarts';

    protected static string|UnitEnum|null $navigationGroup = 'Contrôle de gestion';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Écarts budget et réel';

    protected static ?string $slug = 'ecarts';

    protected string $view = 'filament.pages.ecarts';

    public string $annee = '';

    public string $mois = '';

    public static function canAccess(): bool
    {
        return Acces::finance(auth()->user());
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Effets prix, volume et composition par ligne, avec commentaire du responsable au-delà du seuil';
    }

    public function mount(): void
    {
        $this->initCompagnie();

        $d = today()->copy()->subMonthNoOverflow();
        $this->annee = (string) $d->year;
        $this->mois = (string) $d->month;
    }

    protected function periode(): array
    {
        return [(int) $this->annee, max(1, min(12, (int) $this->mois))];
    }

    protected function getViewData(): array
    {
        $cid = $this->cid();
        [$annee, $mois] = $this->periode();
        $rapports = $this->toutesCompagnies()
            ? collect($this->cids())->mapWithKeys(fn (int $compagnieId): array => [
                $compagnieId => Reporting::mois($compagnieId, $annee, $mois),
            ])
            : collect();

        return [
            'compagnies' => $this->compagniesChoix(),
            'compagnieNoms' => Compagnie::query()->whereIn('id', $this->cids())->pluck('nom', 'id'),
            'rapports' => $rapports,
            'annees' => range(today()->year - 2, today()->year + 1),
            'mois_noms' => $this->nomsMois(),
            'r' => ! $this->toutesCompagnies() && $cid > 0 ? Reporting::mois($cid, $annee, $mois) : null,
            'cloture' => $cid > 0 && CgCloture::where('compagnie_id', $cid)->where('annee', $annee)->where('mois', $mois)->exists(),
        ];
    }

    public function commenterAction(): Action
    {
        return Action::make('commenter')
            ->modalHeading('Commentaire du responsable')
            ->modalDescription('Expliquez l’écart : cause constatée, décision prise ou action prévue.')
            ->modalWidth('2xl')
            ->fillForm(function (array $arguments): array {
                [$annee, $mois] = $this->periode();

                return ['texte' => CgCommentaire::where([
                    'compagnie_id' => $this->cid(), 'annee' => $annee, 'mois' => $mois, 'cle' => (string) ($arguments['cle'] ?? ''),
                ])->value('texte')];
            })
            ->schema([
                Textarea::make('texte')->label('Commentaire')->required()->minLength(10)->maxLength(1000)->rows(4),
            ])
            ->action(function (array $data, array $arguments) {
                abort_unless(Acces::finance(auth()->user()) && $this->cid() > 0, 403);

                [$annee, $mois] = $this->periode();
                $cid = $this->cid();
                $cle = (string) ($arguments['cle'] ?? '');

                if (CgCloture::where('compagnie_id', $cid)->where('annee', $annee)->where('mois', $mois)->exists()) {
                    Notification::make()->title('Mois clôturé')->body('Les commentaires d’un mois clôturé ne changent plus.')->danger()->send();

                    return;
                }

                $r = Reporting::mois($cid, $annee, $mois);
                $cles = collect([...($r['ecarts']['lignes'] ?? []), $r['ecarts']['total'] ?? null])->filter()->pluck('cle');

                abort_unless($cles->contains($cle), 404);

                CgCommentaire::updateOrCreate(
                    ['compagnie_id' => $cid, 'annee' => $annee, 'mois' => $mois, 'cle' => $cle],
                    ['texte' => trim($data['texte']), 'user_id' => auth()->id()],
                );

                Notification::make()->title('Commentaire enregistré')->success()->send();
            });
    }
}
