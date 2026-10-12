<?php

namespace App\Exceptions;

class PlacesInsuffisantes extends \DomainException
{
    public function __construct(public readonly int $restantes)
    {
        parent::__construct($restantes > 0
            ? "Il ne reste que {$restantes} place(s) sur ce départ."
            : 'Ce départ est complet.');
    }
}