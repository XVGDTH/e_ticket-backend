<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CptSection extends Model
{
    protected $table = 'cpt_sections';

    protected $guarded = [];

    protected $casts = ['actif' => 'boolean'];
}
