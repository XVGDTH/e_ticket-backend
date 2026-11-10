<?php

namespace App\Http\Controllers;

use App\Models\DepenseVehicule;
use App\Services\Flotte\Acces;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PieceFlotteController extends Controller
{
    public function show(Request $request, int $depense)
    {
        $user = $request->user();

        abort_unless(Acces::visible($user), 403);

        $d = DepenseVehicule::query()->visiblePour($user)->findOrFail($depense);

        abort_unless($d->piece && Storage::disk('local')->exists($d->piece), 404);

        return Storage::disk('local')->response($d->piece);
    }
}
