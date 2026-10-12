<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Compagnie;

class CompagnieController extends Controller
{
    public function index()
    {
        return Compagnie::where('actif', true)->get(['id', 'nom', 'slug', 'logo_url']);
    }
}