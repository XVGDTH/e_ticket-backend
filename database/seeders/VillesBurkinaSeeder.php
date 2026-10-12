<?php

namespace Database\Seeders;

use App\Models\Ville;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VillesBurkinaSeeder extends Seeder
{
    public function run(): void
    {
        $existantes = [];

        foreach (Ville::all() as $ville) {
            $existantes[$this->cle($ville->nom)] = $ville;
        }

        $crees = 0;
        $completees = 0;

        foreach ($this->villes() as $nom => $aliasSupplementaires) {
            $aliases = array_values(array_unique(array_merge(
                [mb_strtolower($nom), Str::lower(Str::ascii($nom))],
                $aliasSupplementaires
            )));

            $cle = $this->cle($nom);

            if (isset($existantes[$cle])) {
                $ville = $existantes[$cle];
                $actuels = $ville->aliases ?? [];
                $fusion = array_values(array_unique(array_merge($actuels, $aliases)));

                if ($fusion !== $actuels) {
                    $ville->update(['aliases' => $fusion]);
                    $completees++;
                }

                continue;
            }

            Ville::create(['nom' => $nom, 'aliases' => $aliases]);
            $crees++;
        }

        $this->command?->info("Villes créées : {$crees} · villes complétées (alias) : {$completees}.");
    }

    private function cle(string $texte): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($texte)));
    }

    /** nom => alias de recherche supplémentaires */
    private function villes(): array
    {
        return [
            // Chefs-lieux de province, par région
            // Boucle du Mouhoun
            'Boromo' => [], 'Solenzo' => [], 'Nouna' => [], 'Dédougou' => [], 'Toma' => [], 'Tougan' => [],
            // Cascades
            'Banfora' => [], 'Sindou' => [],
            // Centre
            'Ouagadougou' => ['ouaga', 'waga'],
            // Centre-Est
            'Tenkodogo' => [], 'Ouargaye' => [], 'Koupéla' => [],
            // Centre-Nord
            'Kongoussi' => [], 'Boulsa' => [], 'Kaya' => [],
            // Centre-Ouest
            'Koudougou' => [], 'Réo' => [], 'Léo' => [], 'Sapouy' => [],
            // Centre-Sud
            'Kombissiri' => [], 'Pô' => [], 'Manga' => [],
            // Est
            'Bogandé' => [], "Fada N'Gourma" => ['fada'], 'Gayéri' => [], 'Pama' => [], 'Diapaga' => [],
            // Hauts-Bassins
            'Bobo-Dioulasso' => ['bobo'], 'Orodara' => [], 'Houndé' => [],
            // Nord
            'Titao' => [], 'Yako' => [], 'Ouahigouya' => ['ouahi'], 'Gourcy' => [],
            // Plateau-Central
            'Zorgho' => [], 'Boussé' => [], 'Ziniaré' => [],
            // Sahel
            'Gorom-Gorom' => ['gorom'], 'Dori' => [], 'Djibo' => [], 'Sebba' => [],
            // Sud-Ouest
            'Diébougou' => [], 'Dano' => [], 'Batié' => [], 'Gaoua' => [],

            // Autres localités fréquentes
            'Niangoloko' => [], 'Pa' => [], 'Ouessa' => [], 'Hamélé' => [], 'Cinkansé' => [],
            'Kampti' => [], 'Zabré' => [], 'Garango' => [], 'Pouytenga' => [], 'Tikaré' => [],
            'Bittou' => [], 'Nanoro' => [], 'Sabou' => [], 'Koubri' => [], 'Saaba' => [],
            'Ténado' => [], 'Kantchari' => [], 'Mangodara' => [], 'Pissila' => [], 'Barsalogho' => [],
            'Tanghin-Dassouri' => [],
        ];
    }
}