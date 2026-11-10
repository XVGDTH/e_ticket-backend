<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Billet;
use App\Models\DemandeAssistance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AssistanceController extends Controller
{
    public function contact(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'telephone' => config('assistance.telephone'),
            'telephone_affichage' => config('assistance.telephone_affichage'),
            'whatsapp' => config('assistance.whatsapp'),
            'horaires' => config('assistance.horaires'),
        ]);
    }

    public function faq(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'themes' => DemandeAssistance::THEMES,
            'faq' => config('assistance.faq'),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $demandes = DemandeAssistance::query()
            ->where('user_id', $request->user()->id)
            ->with('billet:id,reference')
            ->withCount(['messages as non_lus' => fn ($q) => $q->where('auteur_type', 'staff')->whereNull('lu_le')])
            ->orderByDesc('derniere_activite_le')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'demandes' => $demandes->map(fn ($d) => $this->formater($d))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'theme' => ['required', Rule::in(array_keys(DemandeAssistance::THEMES))],
            'objet' => ['required', 'string', 'min:3', 'max:120'],
            'message' => ['required', 'string', 'min:5', 'max:2000'],
            'billet_reference' => ['nullable', 'string', 'max:40'],
            'rappel' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();

        $ouvertes = DemandeAssistance::where('user_id', $user->id)->where('statut', '!=', 'resolue')->count();

        if ($ouvertes >= (int) config('assistance.max_demandes_ouvertes', 5)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà plusieurs demandes en cours. Complétez-les ou attendez une réponse.',
            ], 429);
        }

        $billet = null;

        if (! empty($data['billet_reference'])) {
            $billet = Billet::with('route')
                ->where('user_id', $user->id)
                ->where('reference', $data['billet_reference'])
                ->first();

            if (! $billet) {
                return response()->json(['success' => false, 'message' => 'Billet introuvable.'], 404);
            }
        }

        $demande = DB::transaction(function () use ($data, $user, $billet) {
            $demande = DemandeAssistance::create([
                'user_id' => $user->id,
                'billet_id' => $billet?->id,
                'compagnie_id' => $billet?->route?->compagnie_id,
                'gare_id' => $billet?->route?->gare_depart_id,
                'theme' => $data['theme'],
                'objet' => $data['objet'],
                'statut' => 'nouvelle',
                'rappel_statut' => ($data['rappel'] ?? false) ? 'demande' : null,
                'derniere_activite_le' => now(),
            ]);

            $demande->messages()->create([
                'auteur_id' => $user->id,
                'auteur_type' => 'client',
                'corps' => $data['message'],
            ]);

            return $demande;
        });

        return response()->json([
            'success' => true,
            'demande' => $this->formater($demande->load(['billet:id,reference', 'messages']), true),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $demande = DemandeAssistance::with(['billet:id,reference', 'messages'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $demande->messages()->where('auteur_type', 'staff')->whereNull('lu_le')->update(['lu_le' => now()]);

        return response()->json(['success' => true, 'demande' => $this->formater($demande, true)]);
    }

    public function repondre(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['corps' => ['required', 'string', 'min:2', 'max:2000']]);

        $demande = DemandeAssistance::where('user_id', $request->user()->id)->findOrFail($id);
        $demande->ajouterMessage($request->user(), $data['corps'], 'client');

        return response()->json([
            'success' => true,
            'demande' => $this->formater($demande->fresh(['billet:id,reference', 'messages']), true),
        ]);
    }

    public function noter(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'integer', 'between:1,5']]);

        $demande = DemandeAssistance::where('user_id', $request->user()->id)->findOrFail($id);

        if ($demande->statut !== 'resolue' || $demande->note !== null) {
            return response()->json(['success' => false, 'message' => 'Cette demande ne peut pas être notée.'], 422);
        }

        $demande->forceFill(['note' => $data['note']])->save();

        return response()->json(['success' => true]);
    }

    private function formater(DemandeAssistance $d, bool $avecMessages = false): array
    {
        $donnees = [
            'id' => $d->id,
            'theme' => $d->theme,
            'theme_label' => DemandeAssistance::THEMES[$d->theme] ?? $d->theme,
            'objet' => $d->objet,
            'statut' => $d->statut,
            'statut_label' => DemandeAssistance::STATUTS[$d->statut] ?? $d->statut,
            'billet_reference' => $d->billet?->reference,
            'rappel' => $d->rappel_statut,
            'note' => $d->note,
            'non_lus' => (int) ($d->non_lus ?? 0),
            'derniere_activite' => ($d->derniere_activite_le ?? $d->created_at)?->toIso8601String(),
        ];

        if ($avecMessages) {
            $donnees['messages'] = $d->messages->map(fn ($m) => [
                'id' => $m->id,
                'staff' => $m->auteur_type === 'staff',
                'corps' => $m->corps,
                'date' => $m->created_at->toIso8601String(),
            ])->values();
        }

        return $donnees;
    }
}
