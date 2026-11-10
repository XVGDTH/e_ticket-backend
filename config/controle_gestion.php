<?php

return [
    // Valeurs de départ, modifiables ensuite pour chaque compagnie (table cg_parametres).
    // À ajuster : ce sont des ordres de grandeur, pas des chiffres de ta compagnie.
    'carburant' => [
        'norme_l100' => 32,   // litres aux 100 km d'un car
        'prix_litre' => 800,  // FCFA le litre
    ],

    // Un écart au-delà de ce pourcentage exige un commentaire du responsable (RG-CDG-06).
    'ecart_seuil_pct' => 10,

    'cles' => [
        'departs' => 'Nombre de départs',
        'voyageurs' => 'Nombre de voyageurs',
        'km' => 'Kilomètres',
        'recettes' => 'Recettes',
    ],
];
