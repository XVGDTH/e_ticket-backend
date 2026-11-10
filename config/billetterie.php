<?php

return [
    // Taxe de service en FCFA : forfait unique par achat, quel que soit le nombre de voyageurs.
    'taxe_par_achat' => (int) env('TAXE_PAR_ACHAT', 300),

    // Un billet reste utilisable jusqu'à ce nombre de jours après l'achat.
    'validite_jours' => (int) env('VALIDITE_JOURS', 12),

    'voyageurs_max' => 10,
    'jours_reservation_max' => 90,
    'qr_secret' => env('TICKET_QR_SECRET'),
    'cgu_version' => '1.0',
    'cloture_auto_heures' => 6,
    'presence_rayon_m' => 400,
];
