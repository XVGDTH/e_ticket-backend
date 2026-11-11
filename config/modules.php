<?php

return [
    'catalogue' => [
        'guichet' => [
            'nom' => 'Ventes au guichet',
            'icone' => 'heroicon-o-ticket',
            'couleur' => '#0d9488',
            'description' => 'Vente de billets en gare, comptée avec les places de l\'application pour ne jamais vendre un siège deux fois.',
            'disponible' => false,
        ],
        'colis' => [
            'nom' => 'Colis et courriers',
            'icone' => 'heroicon-o-archive-box',
            'couleur' => '#f59e0b',
            'description' => 'Enregistrement, suivi et remise des colis et courriers entre les gares, avec reçu et statut.',
            'disponible' => true,
        ],
        'caisse' => [
            'nom' => 'Caisses',
            'icone' => 'heroicon-o-banknotes',
            'couleur' => '#10b981',
            'description' => 'Ouverture et clôture de caisse par agent et par gare, entrées, sorties et écarts.',
            'disponible' => true,
        ],
        'comptabilite' => [
            'nom' => 'Comptabilité',
            'icone' => 'heroicon-o-calculator',
            'couleur' => '#6366f1',
            'description' => 'Recettes, dépenses, journal, bilan et exports pour votre comptable.',
            'disponible' => true,
        ],
        'flotte' => [
            'nom' => 'Flotte et chauffeurs',
            'icone' => 'heroicon-o-truck',
            'couleur' => '#0ea5e9',
            'description' => 'Vos cars, leur capacité réelle, vos chauffeurs et leurs documents.',
            'disponible' => true,
        ],
        'departs' => [
            'nom' => 'Programmation des départs',
            'icone' => 'heroicon-o-calendar-days',
            'couleur' => '#ec4899',
            'description' => 'Affectation d\'un car et d\'un chauffeur à chaque départ, manifeste et feuille de route.',
            'disponible' => false,
        ],
        'personnel' => [
            'nom' => 'Personnel',
            'icone' => 'heroicon-o-users',
            'couleur' => '#8b5cf6',
            'description' => 'Fiches du personnel, présences et paie.',
            'disponible' => true,
        ],
        'controle_gestion' => [
            'nom' => 'Contrôle de gestion',
            'icone' => 'heroicon-o-chart-bar-square',
            'couleur' => '#0891b2',
            'description' => 'Rentabilité par départ, car, ligne, gare et activité : modèle de coûts, charges de structure et écarts.',
            'disponible' => true,
        ],
        'audit_interne' => [
            'nom' => 'Contrôle interne et audit',
            'icone' => 'heroicon-o-clipboard-document-check',
            'couleur' => '#0f766e',
            'description' => 'Plan de contrôles internes, responsables et suivi des recommandations d’audit.',
            'disponible' => true,
        ],
        'maintenance' => [
            'nom' => 'Maintenance et carburant',
            'icone' => 'heroicon-o-wrench-screwdriver',
            'couleur' => '#ef4444',
            'description' => 'Entretien des cars, pannes, pleins de carburant et coût par kilomètre.',
            'disponible' => true,
        ],
        'promotions' => [
            'nom' => 'Promotions',
            'icone' => 'heroicon-o-gift',
            'couleur' => '#f97316',
            'description' => 'Codes promo, réductions et offres sur certains trajets.',
            'disponible' => false,
        ],
    ],

    /*
     * Rôles métiers : chaque rôle donne accès à un ou plusieurs modules du catalogue.
     * Les clés de 'modules' doivent exister dans 'catalogue' ci-dessus.
     */
    'roles' => [
        'rh' => ['label' => 'Ressources humaines', 'modules' => ['personnel']],
        'caisse' => ['label' => 'Caissier', 'modules' => ['caisse']],
        'comptable' => ['label' => 'Comptable', 'modules' => ['comptabilite']],
        'flotte' => ['label' => 'Flotte', 'modules' => ['flotte', 'maintenance']],
        'controleur_gestion' => ['label' => 'Contrôleur de gestion', 'modules' => ['controle_gestion']],

        'auditeur_interne' => [
            'label' => 'Auditeur interne',
            'modules' => ['audit_interne', 'comptabilite', 'caisse', 'personnel', 'flotte', 'maintenance', 'controle_gestion'],
            'lecture_seule' => true,
            'ecriture_modules' => ['audit_interne'],
            'incompatibles' => ['caisse', 'comptable'],
        ],
        

    ],
];