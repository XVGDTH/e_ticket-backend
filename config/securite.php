<?php

return [
    // Double authentification obligatoire pour les super admins et les admins de compagnie.
    // À activer (MFA_OBLIGATOIRE_ADMINS=true dans .env) seulement après avoir testé la configuration.
    'mfa_obligatoire_admins' => (bool) env('MFA_OBLIGATOIRE_ADMINS', false),
];