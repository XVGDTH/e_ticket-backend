<?php

namespace Tests\Feature;

use App\Models\Compagnie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompteDesactiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_compte_desactive_na_plus_acces_au_panel(): void
    {
        $compagnie = Compagnie::create(['nom' => 'CompagnieTest', 'slug' => 'compagnie-test', 'actif' => true]);

        $admin = User::create([
            'prenom' => 'Admin',
            'nom' => 'Test',
            'telephone' => '74000001',
            'telephone_verifie' => true,
            'password' => 'motdepasse123',
        ]);
        $admin->assignerRolePourCompagnie('admin_compagnie', $compagnie->id);

        $this->actingAs($admin)->get('/admin')->assertOk();

        $admin->update(['actif' => false]);

        $this->actingAs($admin->fresh())->get('/admin')->assertForbidden();
    }
}