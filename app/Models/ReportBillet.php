<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Trace de chaque report : de quel départ vers quel départ. */
class ReportBillet extends Model
{
    protected $table = 'reports_billets';

    protected $fillable = [
        'billet_id', 'route_transport_id',
        'ancienne_date', 'ancienne_heure', 'nouvelle_date', 'nouvelle_heure',
    ];

    protected $casts = ['ancienne_date' => 'date', 'nouvelle_date' => 'date'];

    public function billet(): BelongsTo
    {
        return $this->belongsTo(Billet::class);
    }
}
