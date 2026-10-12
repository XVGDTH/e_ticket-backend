<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUpTraits(): array
    {
        // Sécurité : les tests ne tournent que sur une base dont le nom finit par "_test".
        $connexion = config('database.default');
        $base = (string) config("database.connections.{$connexion}.database");

        if (! str_ends_with(strtolower($base), '_test')) {
            throw new \RuntimeException("Tests bloqués : la base '{$base}' n'est pas une base de test.");
        }

        return parent::setUpTraits();
    }
}