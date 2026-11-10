<?php

namespace App\Http\Controllers;

use App\Models\FactureFournisseur;
use App\Services\AuditInterne\Acces;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PieceFactureFournisseurController extends Controller
{
    public function show(Request $request, int $facture): StreamedResponse
    {
        $utilisateur = $request->user();

        abort_unless(Acces::visible($utilisateur), 403);

        $requete = FactureFournisseur::query()->whereKey($facture);

        if (! $utilisateur->isSuperAdmin()) {
            $requete->where('compagnie_id', $utilisateur->compagnieId() ?? 0);
        }

        $piece = $requete->value('piece_path');

        abort_unless($piece && Storage::disk('local')->exists($piece), 404);

        return Storage::disk('local')->response($piece);
    }
}
