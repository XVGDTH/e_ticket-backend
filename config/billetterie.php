<?php

return [
    // Taxe par voyageur, en FCFA. null = règle non définie : la taxe envoyée par l'application
    // est acceptée si elle ne dépasse pas 20 % du montant. Définis TAXE_PAR_VOYAGEUR dans .env pour l'imposer.
    'taxe_par_voyageur' => env('TAXE_PAR_VOYAGEUR') === null ? null : (int) env('TAXE_PAR_VOYAGEUR'),

    'voyageurs_max' => 10,
    'jours_reservation_max' => 90,
];