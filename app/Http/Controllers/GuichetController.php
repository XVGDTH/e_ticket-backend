<?php

namespace App\Http\Controllers;

use App\Enums\BilletStatut;
use App\Models\Billet;
use App\Models\Caisse;
use App\Models\RouteTransport;
use App\Models\User;
use App\Services\Billetterie;
use App\Services\BilletToken;
use App\Services\Guichet\AccesGuichet;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Documents imprimables du guichet : billet, manifeste de départ, état de caisse. */
class GuichetController extends Controller
{
    public function billet(Request $request, int $id)
    {
        $u = auth()->user();
        abort_unless(AccesGuichet::visible($u), 403);

        $b = Billet::query()->visiblesPar($u)->where('canal', 'guichet')
            ->with(['route.compagnie', 'route.villeArrivee', 'gare.ville'])->findOrFail($id);

        $duplicata = $b->impressions > 0;
        DB::table('billets')->where('id', $b->id)->increment('impressions'); // sans toucher updated_at

        $qr = null;

        if (class_exists(Writer::class)) {
            $jeton = rescue(fn () => BilletToken::generer($b), $b->reference, false);
            $svg = (new Writer(new ImageRenderer(
                new RendererStyle(180, 0),
                new SvgImageBackEnd
            )))->writeString((string) $jeton);
            $qr = preg_replace('/^<\?xml.*?\?>\s*/s', '', $svg);
        }

        return view('guichet.billet', [
            'b' => $b, 'qr' => $qr, 'duplicata' => $duplicata,
            'agent' => $b->vendu_par ? User::find($b->vendu_par) : null,
            'format' => $request->query('format') === 'a5' ? 'a5' : 'thermique',
        ]);
    }

    public function manifeste(Request $request)
    {
        $u = auth()->user();
        abort_unless(AccesGuichet::visible($u), 403);

        $d = $request->validate(['route' => 'required|integer', 'date' => 'required|date', 'heure' => 'required|string|max:10']);
        $heure = Billetterie::normaliserHeure($d['heure']);

        $route = RouteTransport::with(['gareDepart.ville', 'villeArrivee', 'compagnie'])->findOrFail($d['route']);

        $billets = Billet::query()->visiblesPar($u)->where('route_transport_id', $route->id)->whereDate('date_voyage', $d['date'])
            ->whereIn('statut', [BilletStatut::Valide->value, BilletStatut::Utilise->value])->orderBy('created_at')->get()
            ->filter(fn ($b) => Billetterie::normaliserHeure((string) $b->heure) === $heure);

        return view('guichet.manifeste', ['route' => $route, 'billets' => $billets, 'date' => Carbon::parse($d['date']), 'heure' => $heure]);
    }

    public function caisse(int $id)
    {
        $u = auth()->user();
        abort_unless(AccesGuichet::visible($u), 403);

        $c = Caisse::query()->visiblePour($u)->with(['user', 'gare.ville', 'mouvements.billet'])->findOrFail($id);

        return view('guichet.etat-caisse', ['c' => $c]);
    }
}
