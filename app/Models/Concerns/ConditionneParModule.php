<?php

namespace App\Filament\Concerns;

use App\Services\Modules;

trait ConditionneParModule
{
    /** Clé du module dans config/modules.php, par exemple 'colis'. */
    abstract protected static function moduleCle(): string;

    public static function moduleVisible(): bool
    {
        return Modules::visiblePour(auth()->user(), static::moduleCle());
    }
}
