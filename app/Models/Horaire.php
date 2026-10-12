<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Horaire extends Model
{
    use HasFactory;

    protected $fillable = ['route_transport_id', 'heure', 'jours_semaine'];

    protected $casts = ['jours_semaine' => 'array'];

    public function route(): BelongsTo
    {
        return $this->belongsTo(RouteTransport::class, 'route_transport_id');
    }
}