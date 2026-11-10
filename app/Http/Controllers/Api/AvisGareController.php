<?php

namespace App\Http\Controllers\Api;

use App\Enums\BilletStatut;
use App\Http\Controllers\Controller;
use App\Models\AvisGare;
use App\Services\Billetterie;
use Illuminate\Http\Request;

class AvisGareController extends Controller
{
    /** Les six critères de l'application ; chaque score va de 0 à 2. */
    private const CRITERES = ['ponctualite', 'proprete', 'bruit', 'arrets', 'tracasseries', 'courtoisie'];

    public function store(Request $request)
    {
        $data = $request->validate([
            'billet_reference' => ['required', 'string', 'max:50'],
            'scores' => ['required', 'array'],
            'scores.*' => ['integer', 'between:0,2'],
            'commentaire' => ['nullable', 'string', 'max:1000'],
        ]);

        $cles = array_keys($data['scores']);

        if (array_diff(self::CRITERES, $cles) || array_diff($cles, self::CRITERES)) {
            return $this->refus('Tous les critères doivent être notés.');
        }

        // Le billet doit appartenir au client connecté.
        $billet = $request->user()->billets()->where('reference', $data['billet_reference'])->first();

        if (! $billet) {
            return $this->refus('Billet introuvable.', 404);
        }

        if (! in_array($billet->statut, [BilletStatut::Valide, BilletStatut::Utilise], true)) {
            return $this->refus('Ce billet ne peut pas être noté.');
        }

        $depart = $billet->date_voyage->copy()
            ->setTimeFromTimeString(Billetterie::normaliserHeure((string) $billet->heure));

        if (now()->lt($depart)) {
            return $this->refus('Vous pourrez donner votre avis après le départ.');
        }

        if (AvisGare::where('billet_id', $billet->id)->exists()) {
            return $this->refus('Un avis a déjà été envoyé pour ce billet.', 409);
        }

        // La gare est fixée par le modèle à partir du billet.
        AvisGare::create([
            'billet_id' => $billet->id,
            'scores' => $data['scores'],
            'commentaire' => trim((string) ($data['commentaire'] ?? '')) ?: null,
        ]);

        return response()->json(['success' => true], 201);
    }

    private function refus(string $message, int $statut = 422)
    {
        return response()->json(['success' => false, 'message' => $message], $statut);
    }
}
