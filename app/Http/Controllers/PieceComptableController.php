<?php

namespace App\Http\Controllers;

use App\Models\CptEcriture;
use App\Services\Modules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PieceComptableController extends Controller
{
    public function show(Request $request, int $ecriture, int $index)
    {
        $user = $request->user();

        abort_unless(Modules::visiblePour($user, 'comptabilite'), 403);

        $e = CptEcriture::query()->visiblePour($user)->findOrFail($ecriture);
        $chemin = ($e->pieces ?? [])[$index] ?? null;

        abort_unless($chemin && Storage::disk('local')->exists($chemin), 404);

        return Storage::disk('local')->response($chemin);
    }
}
