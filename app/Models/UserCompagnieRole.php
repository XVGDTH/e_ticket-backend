<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class UserCompagnieRole extends Model
{
    use HasFactory;

    // protected $fillable = ['user_id', 'compagnie_id', 'role_id']; 
        protected $fillable = ['user_id', 'compagnie_id', 'gare_id', 'ville_id', 'role_id'];

    public function ville(): BelongsTo
    {
        return $this->belongsTo(Ville::class);
    }

    public function gare(): BelongsTo
    {
        return $this->belongsTo(Gare::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}