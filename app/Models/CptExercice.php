<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CptExercice extends Model
{
    protected $table = 'cpt_exercices';

    protected $guarded = [];

    protected $casts = ['date_debut' => 'date', 'date_fin' => 'date', 'cloture' => 'boolean'];
}
