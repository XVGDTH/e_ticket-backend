<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationClient;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            NotificationClient::where('user_id', $request->user()->id)
                ->latest()
                ->limit(200)
                ->get()
                ->map(fn (NotificationClient $n) => [
                    'id' => $n->id,
                    'type' => $n->type,
                    'titre' => $n->titre,
                    'corps' => $n->corps,
                    'date' => $n->created_at->toIso8601String(),
                    'billet_reference' => $n->billet_reference,
                    'lu' => $n->lu_a !== null,
                ])
                ->values()
        );
    }

    public function lire(Request $request, NotificationClient $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        $notification->forceFill(['lu_a' => $notification->lu_a ?? now()])->save();

        return response()->json(['success' => true]);
    }

    public function toutLire(Request $request)
    {
        NotificationClient::where('user_id', $request->user()->id)
            ->whereNull('lu_a')
            ->update(['lu_a' => now()]);

        return response()->json(['success' => true]);
    }

    /** Suppression logique : disparue de l'application, conservée en base. */
    public function destroy(Request $request, NotificationClient $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        $notification->delete();

        return response()->json(['success' => true]);
    }
}
