<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 26px 28px 44px 28px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #0f172a; }
    .bandeau { background: #0f766e; color: #fff; padding: 16px 20px; border-radius: 10px; }
    .bandeau h1 { margin: 0 0 4px 0; font-size: 18px; }
    .bandeau p { margin: 0; font-size: 9px; opacity: .85; }
    table { width: 100%; border-collapse: collapse; }
    .meta td { padding: 3px 6px 3px 0; font-size: 9px; }
    .meta td.k { color: #64748b; width: 110px; }
    .kpis td { width: 25%; padding: 4px; }
    .kpi { background: #f0fdfa; border: 1px solid #99f6e4; border-radius: 8px; padding: 8px 10px; }
    .kpi .k { color: #475569; font-size: 8px; text-transform: uppercase; letter-spacing: .06em; }
    .kpi .v { font-size: 14px; font-weight: bold; color: #0f766e; margin-top: 3px; }
    h2 { font-size: 12px; color: #0f766e; margin: 18px 0 6px 0; border-bottom: 2px solid #f59e0b; padding-bottom: 3px; }
    .donnees th { background: #0f766e; color: #fff; padding: 5px 6px; text-align: left; font-size: 8px; }
    .donnees td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; }
    .donnees tr.z td { background: #f8fafc; }
    .donnees tr.total td { background: #fef3c7; font-weight: bold; border-top: 2px solid #f59e0b; }
    .n { text-align: right; }
    .note { color: #b45309; font-size: 8px; margin-top: 4px; }
    .pied { position: fixed; bottom: -28px; left: 0; right: 0; text-align: center; color: #94a3b8; font-size: 8px; }
    .pied:after { content: "Page " counter(page); }
</style>
</head>
<body>
    <div class="bandeau">
        <h1>{{ $titre }}</h1>
        <p>Rapport confidentiel, réservé à la gestion financière</p>
    </div>

    <table class="meta" style="margin-top: 10px;">
        @foreach ($meta as $cle => $valeur)
            <tr><td class="k">{{ $cle }}</td><td>{{ $valeur }}</td></tr>
        @endforeach
    </table>

    @if (count($kpis))
        <table class="kpis" style="margin-top: 8px;">
            @foreach (array_chunk($kpis, 4) as $rangee)
                <tr>
                    @foreach ($rangee as [$cle, $valeur])
                        <td><div class="kpi"><div class="k">{{ $cle }}</div><div class="v">{{ $valeur }}</div></div></td>
                    @endforeach
                    @for ($i = count($rangee); $i < 4; $i++) <td></td> @endfor
                </tr>
            @endforeach
        </table>
    @endif

    @foreach ($sections as $section)
        <h2>{{ $section['titre'] }}</h2>
        <table class="donnees">
            <thead>
                <tr>@foreach ($section['entetes'] as $entete)<th>{{ $entete }}</th>@endforeach</tr>
            </thead>
            <tbody>
                @foreach ($section['lignes'] as $i => $ligne)
                    <tr class="{{ ($ligne[0] ?? '') === 'TOTAL' ? 'total' : ($i % 2 ? 'z' : '') }}">
                        @foreach ($ligne as $cellule)
                            <td class="{{ is_int($cellule) || is_float($cellule) ? 'n' : '' }}">{{ is_int($cellule) || is_float($cellule) ? number_format($cellule, 0, ',', ' ') : $cellule }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if (! empty($section['tronque']))
            <div class="note">Affichage limité aux premières lignes. Utilisez l'export Excel ou CSV pour la liste complète.</div>
        @endif
    @endforeach

    <div class="pied">Généré le {{ now()->format('d/m/Y H:i') }} · </div>
</body>
</html>