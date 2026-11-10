<?php

namespace App\Http\Controllers;

use App\Models\BulletinPaie;
use App\Models\Employe;
use App\Services\Personnel\AccesPersonnel;

/** Documents imprimables : bulletin de paie et attestation de travail. */
class PersonnelDocumentsController extends Controller
{
    public function bulletin(int $id)
    {
        $u = auth()->user();
        abort_unless(AccesPersonnel::paie($u), 403);

        $b = BulletinPaie::query()->visiblePour($u)->with(['employe.gare.ville', 'periode', 'compagnie', 'contrat'])->findOrFail($id);

        return view('personnel.bulletin', ['b' => $b, 'e' => $b->employe]);
    }

    public function attestation(int $id)
    {
        $u = auth()->user();
        abort_unless(AccesPersonnel::gerant($u), 403);

        $e = Employe::query()->visiblePour($u)->with(['compagnie', 'gare.ville'])->findOrFail($id);

        return view('personnel.attestation', ['e' => $e]);
    }
}
