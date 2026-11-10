<?php

return [
    'alerte_jours' => 30,          // documents et permis : alerte à 30 jours de l'expiration
    'vidange_marge_km' => 500,     // alerte vidange 500 km avant l'échéance
    'min_voyages_verdict' => 5,    // en dessous, le verdict reste « données insuffisantes »
    'marge_rentable' => 10,        // % de marge à partir duquel un car est « rentable »
    'remplissage_bas' => 50,       // % : en dessous, départs à revoir
    'remplissage_haut' => 90,      // % : au-dessus, départs presque pleins
    'entretien_part_max' => 15,    // % des recettes consacrés à l'entretien et aux réparations
    'ecart_carburant' => 15,       // % au-dessus de la consommation moyenne de la flotte
    'age_remplacement_ans' => 10,  // âge à partir duquel on étudie le remplacement
    'sous_utilisation' => 60,      // % du nombre moyen de voyages de la flotte
    'km_min_consommation' => 300,  // km minimum pour calculer une consommation
];
