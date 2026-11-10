<?php

namespace App\Console\Commands;

use App\Models\Compagnie;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImporterLogosCompagnies extends Command
{
    protected $signature = 'logos:importer';

    protected $description = 'Rattache les logos de storage/app/public/logos aux compagnies (nom du fichier = identifiant de la compagnie).';

    public function handle(): int
    {
        $disque = Storage::disk('public');
        $extensions = ['png', 'webp', 'jpg', 'jpeg', 'svg'];

        foreach (Compagnie::orderBy('nom')->get() as $compagnie) {
            $fichier = collect($extensions)
                ->map(fn (string $ext) => "logos/{$compagnie->slug}.{$ext}")
                ->first(fn (string $chemin) => $disque->exists($chemin));

            if ($fichier) {
                $compagnie->update(['logo_url' => $fichier]);
                $this->info("{$compagnie->nom} : {$fichier}");
            } else {
                $this->warn("{$compagnie->nom} : aucun fichier logos/{$compagnie->slug}.(png|webp|jpg|svg)");
            }
        }

        return self::SUCCESS;
    }
}
