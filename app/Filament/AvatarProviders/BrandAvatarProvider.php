<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class BrandAvatarProvider implements AvatarProvider
{
    public function get(Model | Authenticatable $record): string
    {
        $name = Filament::getNameForDefaultAvatar($record);

        $initials = collect(preg_split('/\s+/u', trim($name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
            . '<stop offset="0" stop-color="' . config('brand.primary') . '"/>'
            . '<stop offset="0.75" stop-color="' . config('brand.secondary') . '"/>'
            . '</linearGradient></defs>'
            . '<rect width="64" height="64" fill="url(#g)"/>'
            . '<text x="32" y="32" dy=".35em" text-anchor="middle" fill="#fff" '
            . 'font-family="Arial, sans-serif" font-size="26" font-weight="700">'
            . e($initials) . '</text></svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}