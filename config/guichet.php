<?php

return [
    'jours_vente_max' => 30,                    // on peut vendre jusqu'à N jours à l'avance
    'vente_fermee_minutes_avant' => 0,          // arrêt des ventes N minutes avant le départ
    'remise_max_pct_agent' => 10,               // remise maximale d'un agent ; un gérant n'a pas de limite
    'delai_annulation_agent_minutes' => 30,     // un agent annule sa vente dans ce délai, caisse ouverte
    'nom_obligatoire' => false,                 // sinon « Passager 1 », « Passager 2 »…
    'ecart_tolerance' => 0,                     // écart de caisse (FCFA) accepté sans explication

    'modes' => [
        'especes' => 'Espèces',
        'orange_money' => 'Orange Money',
        'moov_money' => 'Moov Money',
        'coris_money' => 'Coris Money',
        'autre' => 'Autre',
    ],
    'modes_avec_reference' => ['orange_money', 'moov_money', 'coris_money'], // numéro de transaction obligatoire

    'conditions' => 'Présentez-vous 30 minutes avant le départ avec une pièce d\'identité. Billet personnel.',
];
