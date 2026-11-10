<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingDestinataire extends Model
{
    protected $table = 'marketing_destinataires';

    protected $guarded = [];

    protected $casts = ['envoye_le' => 'datetime', 'remis_le' => 'datetime', 'lu_le' => 'datetime'];
}
