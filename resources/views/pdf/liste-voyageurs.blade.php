<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .sous { color: #555; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; }
        th { background: #eee; }
    </style>
</head>
<body>
    <h1>{{ $titre }}</h1>
    <div class="sous">{{ count($lignes) }} voyageur(s) · édité le {{ now()->format('d/m/Y à H:i') }}</div>

    <table>
        <thead>
            <tr>
                @foreach ($entetes as $entete)
                    <th>{{ $entete }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($lignes as $ligne)
                <tr>
                    @foreach ($ligne as $cellule)
                        <td>{{ $cellule }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>