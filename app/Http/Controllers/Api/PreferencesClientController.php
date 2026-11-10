<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Marketing\Preferences;
use Illuminate\Http\Request;

class PreferencesClientController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'compagnies' => Preferences::classementClient($request->user()->id),
        ]);
    }
}
