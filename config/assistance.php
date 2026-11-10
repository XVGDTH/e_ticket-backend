<?php

return [
    'telephone' => '+22654785555',
    'telephone_affichage' => '54 78 55 55',
    'whatsapp' => '22654785555',
    'horaires' => '', // ex. : « Du lundi au samedi, de 8h à 18h »

    'max_demandes_ouvertes' => 5,

    'faq' => [
        ['theme' => 'paiement', 'q' => 'Que sont les frais de 300 FCFA ?',
            'r' => 'Ce sont des frais de service payés une seule fois par achat, quel que soit le nombre de billets. Tous frais payés ne sont plus remboursables.'],
        ['theme' => 'paiement', 'q' => 'Mon paiement est parti mais je n\'ai pas de billet',
            'r' => 'Patientez quelques minutes, puis tirez l\'écran « Mes billets » vers le bas pour l\'actualiser. Si le billet n\'apparaît toujours pas, écrivez-nous ici en indiquant l\'opérateur et l\'heure du paiement.'],
        ['theme' => 'billet', 'q' => 'Combien de temps mon billet est-il valable ?',
            'r' => 'Votre billet reste utilisable à partir du jour du voyage choisi, pendant la durée fixée par la compagnie (12 jours après l\'achat par défaut). La date limite est affichée sur le billet.'],
        ['theme' => 'billet', 'q' => 'Comment utiliser mon billet à la gare ?',
            'r' => 'Présentez-vous à la gare avant le départ : l\'agent de la gare vous fait embarquer et vous n\'avez rien à scanner. Vous pouvez montrer le code QR de votre billet si l\'agent le demande.'],
        ['theme' => 'report', 'q' => 'Comment reporter mon voyage ?',
            'r' => 'Ouvrez le billet et touchez « Reporter mon voyage », puis choisissez un jour et un départ de la même route, dans la durée de validité de votre billet. Aucun motif n\'est demandé.'],
        ['theme' => 'report', 'q' => 'J\'ai raté mon départ, que faire ?',
            'r' => 'Votre billet reste valable. Dans « À venir », il est marqué « À reporter » : reportez-le sur un autre départ avant la date limite. Si le départ n\'est pas encore clôturé par la gare, réessayez dans quelques heures.'],
        ['theme' => 'report', 'q' => 'Puis-je être remboursé ?',
            'r' => 'Les conditions de remboursement du prix du billet sont fixées par chaque compagnie. Les frais de service ne sont pas remboursables. Écrivez-nous pour étudier votre cas.'],
        ['theme' => 'voyage', 'q' => 'Pourquoi un départ n\'apparaît-il plus ?',
            'r' => 'Le départ est complet, la part de places vendues par l\'application est atteinte, ou l\'heure est passée. Essayez un autre horaire ou un autre jour.'],
        ['theme' => 'voyage', 'q' => 'Mon billet est marqué « Terminé » alors que je n\'ai pas voyagé',
            'r' => 'Écrivez-nous tout de suite en indiquant la référence du billet : la gare peut corriger l\'embarquement le jour même.'],
        ['theme' => 'autre', 'q' => 'Comment changer mon nom ou mon mot de passe ?',
            'r' => 'Votre nom se modifie dans le Profil. Pour le mot de passe, utilisez « Mot de passe oublié » sur l\'écran de connexion.'],
    ],
];
