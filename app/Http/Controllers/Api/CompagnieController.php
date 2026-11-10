<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Compagnie;
use App\Models\Gare;
use App\Models\Ville;
use Illuminate\Http\Request;

class CompagnieController extends Controller
{
    public function index()
    {
        return Compagnie::where('actif', true)->get(['id', 'nom', 'slug', 'logo_url']);
    }

    /** Villes de départ où la compagnie a au moins une route active (nationale ou internationale). */
    public function villesDepart(Request $request, Compagnie $compagnie)
    {
        if (! $compagnie->actif) {
            return response()->json([]);
        }

        $type = $request->query('type') === 'international' ? 'international' : 'national';

        $gareIds = $compagnie->routes()->where('actif', true)->duType($type)
            ->pluck('gare_depart_id')->unique()->values();
        $villeIds = Gare::whereIn('id', $gareIds)->pluck('ville_id')->unique()->values();

        return response()->json(Ville::whereIn('id', $villeIds)->orderBy('nom')->pluck('nom')->values());
    }
}
