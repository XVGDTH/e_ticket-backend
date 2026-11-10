<?php

namespace App\Filament\Pages;

use App\Models\Compagnie;
use App\Models\MarketingCampagne;
use App\Models\PreferenceClient;
use App\Models\User;
use App\Services\Marketing\Preferences;
use App\Services\Marketing\WhatsAppCloud;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class MarketingTableau extends Page
{
    protected string $view = 'filament.pages.marketing-tableau';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Tableau Marketing';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 1;

    public ?array $etat = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public function getTitle(): string
    {
        return 'Marketing WhatsApp';
    }

    public function verifierConnexion(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->etat = WhatsAppCloud::etatNumero();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recalculer')
                ->label('Recalculer les préférences')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalDescription('Les notes et les types de clients sont recalculés à partir de tous les billets. Cela prend quelques secondes.')
                ->action(function () {
                    abort_unless(static::canAccess(), 403);

                    $r = Preferences::recalculer();

                    Notification::make()
                        ->title('Préférences recalculées')
                        ->body($r['segments']['fidele'].' fidèle(s), '.$r['segments']['regulier'].' régulier(s), '.$r['segments']['occasionnel'].' occasionnel(s), '.$r['segments']['inactif'].' inactif(s).')
                        ->success()->send();
                }),
        ];
    }

    public function donnees(): array
    {
        // $clients = User::query()->whereDoesntHave('compagnieRoles')->where('telephone_verifie', true);

        // $clients = User::query()
        //     ->whereDoesntHave('compagnieRoles.role', fn ($r) => $r->whereIn('name', ['super_admin', 'admin_compagnie', 'agent_gare', 'service_client']))
        //     ->where('telephone_verifie', true);
        $clients = User::query()
            ->whereDoesntHave('compagnieRoles.role', fn ($r) => $r->whereIn('name', ['super_admin', 'admin_compagnie', 'agent_gare', 'service_client']))
            ->where('telephone_verifie', true);
        $parSegment = PreferenceClient::query()->where('gare_id', 0)
            ->selectRaw('segment, COUNT(DISTINCT user_id) as n')->groupBy('segment')->pluck('n', 'segment');

        $noms = Compagnie::pluck('nom', 'id');

        $top = PreferenceClient::query()->where('gare_id', 0)
            ->whereIn('segment', ['fidele', 'regulier'])
            ->whereHas('user', fn ($q) => $q->where('marketing_whatsapp', true))
            ->selectRaw('compagnie_id, COUNT(*) as n')->groupBy('compagnie_id')->orderByDesc('n')->limit(8)->get()
            ->map(fn ($r) => ['nom' => $noms[$r->compagnie_id] ?? '—', 'n' => (int) $r->n])->all();

        $joignables = collect($top)->sum('n');

        return [
            'clients' => (clone $clients)->count(),
            'consentants' => (clone $clients)->where('marketing_whatsapp', true)->count(),
            'avec_billets' => PreferenceClient::where('gare_id', 0)->distinct()->count('user_id'),
            'segments' => [
                'fidele' => (int) ($parSegment['fidele'] ?? 0),
                'regulier' => (int) ($parSegment['regulier'] ?? 0),
                'occasionnel' => (int) ($parSegment['occasionnel'] ?? 0),
                'inactif' => (int) ($parSegment['inactif'] ?? 0),
            ],
            'top' => $top,
            'joignables' => (int) $joignables,
            'campagnes' => MarketingCampagne::with(['compagnie', 'gare'])->latest()->limit(5)->get(),
            // 'mode' => config('marketing.mode'),
            'configure' => WhatsAppCloud::configure(),
            'webhook' => url('/api/whatsapp/webhook'),
        ];
    }
}
