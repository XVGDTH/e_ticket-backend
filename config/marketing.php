<?php

return [
    'indicatif' => '226',

    'segments' => [
        'fidele_score' => 60,
        'fidele_billets' => 3,
        'regulier_score' => 35,
        'regulier_billets' => 2,
        'inactif_jours' => 180,
    ],

    'pause_jours' => 7,   // un client ne reçoit pas plus d'une campagne WhatsApp par période
    'lot' => 100,         // messages WhatsApp envoyés à chaque passage (chaque minute)
    'cout_message_fcfa' => (int) env('WHATSAPP_COUT_MESSAGE_FCFA', 0),

    'mots_stop' => ['stop', 'arret', 'arrêt', 'desabonner', 'désabonner', 'desabonnement', 'unsubscribe', 'non merci', 'ne plus recevoir'],
    'mots_start' => ['start', 'reabonner', 'réabonner'],
];
