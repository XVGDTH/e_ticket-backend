<?php

/*
 * Paramètres du module Personnel.
 */
return [
    'matricule_prefixe' => 'EMP',
    'alerte_jours' => 30,                 // contrats, documents, essais
    'smig_mensuel' => 45000,

    'horaire' => [
        'debut' => '07:30',
        'fin' => '17:00',
        'tolerance_minutes' => 10,        // au-delà : retard
    ],
    // gestion des congés du personnel

    'conges' => [
        'jours_par_mois' => 2.5,
        'maternite_jours' => 98,        // soit 14 semaines
    ],
    //  le pourcentage mensuel pour le remboursement d’une avance consentie par l’employeur
    'avances' => [
        'assiette' => 'quotite_cessible', // après déductions légales/exclusions
        'tranches' => [
            [75000, 33.33],
            [100000, 40],
            [200000, 45],
            [300000, 50],
            [null, 55],
        ],
    ],

    'paie' => [
        'jours_mois' => 30,               // diviseur pour les retenues d'absence
        'heures_jour' => 8,
        // majoration des heures supplementaires de la 41e à la 48e heure.

        'majoration_hs_pct' => 15,
        // taxe patronale d'apprentissage
        'tpa_pct' => 3,
        'statuts_non_payes' => ['absent_injustifie', 'mise_a_pied', 'conge_sans_solde'],
        'statuts_journalier' => ['present', 'retard', 'mission'],
    ],

    'cnss' => [
        'plafond' => 800000,
        // % retenu sur le salaire brut plafonné avec part salarié= base cnss * 5.5%
        'salarie_pct' => 5.5,
        // % à la charge de l'employeur avec part employeur = base cnss * 16%
        'employeur_pct' => 16.0,
    ],

    'iuts' => [
        //  % abattement forfaitaire appliqué avant le bareme : 25% du salaire de base pour la plupart des salariés et 20% pour les cadres et superieur
        'abattement_pct' => 25,
        // tranche des revenu mensuel imposable avec l'iuts pour chaque tranche
        'tranches' => [
            [30000, 0],
            [50000, 12.1],
            [80000, 13.9],
            [120000, 15.7],
            [170000, 18.4],
            [250000, 21.7],
            [null, 25],
        ],
        'reductions' => [0 => 0, 1 => 8, 2 => 10, 3 => 12, 4 => 14], // % selon les charges de famille
        'max_charges' => 4,
    ],
];
