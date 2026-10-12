<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    use HasFactory;

    protected $fillable = ['telephone', 'code', 'expire_a', 'utilise'];

    protected $casts = [
        'expire_a' => 'datetime',
        'utilise' => 'boolean',
    ];

    public static function genererPour(string $telephone): self
    {
        static::where('telephone', $telephone)->where('utilise', false)->update(['utilise' => true]);

        return static::create([
            'telephone' => $telephone,
            'code' => (string) random_int(100000, 999999),
            'expire_a' => now()->addMinutes(10),
            'utilise' => false,
        ]);
    }

    public function estValide(string $code): bool
    {
        return !$this->utilise && $this->code === $code && now()->isBefore($this->expire_a);
    }
}