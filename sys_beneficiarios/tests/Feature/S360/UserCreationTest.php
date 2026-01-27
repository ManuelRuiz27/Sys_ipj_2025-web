<?php

namespace Tests\Feature\S360;

use App\Models\User;
use Database\Seeders\Salud360RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserCreationTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->seed(Salud360RolesSeeder::class);
    }

    public function test_admin_creates_users_payload(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $payload = [
            'users' => [
                ['name' => 'Enc 360 A', 'email' => 'e360a@example.com', 'role' => 'encargado_360'],
                ['name' => 'Bienestar A', 'email' => 'bienA@example.com', 'role' => 'encargado_bienestar'],
            ],
        ];

        $this->actingAs($admin)
            ->postJson('/s360/admin/users', $payload)
            ->assertCreated()
            ->assertJsonStructure(['ok','users'=>[['id','name','email','role']]]);

        $this->assertDatabaseHas('users', ['email' => 'e360a@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'bienA@example.com']);
    }

    public function test_bienestar_creates_enc360(): void
    {
        $bien = User::factory()->create();
        $bien->assignRole('encargado_bienestar');

        $this->actingAs($bien)
            ->postJson('/s360/bienestar/enc360', ['name' => 'Nvo Enc360', 'email' => 'nvoe360@example.com'])
            ->assertCreated()
            ->assertJsonPath('user.role', 'encargado_360');
    }

    public function test_enc360_cannot_create_psico_without_permission(): void
    {
        $enc = User::factory()->create();
        $enc->assignRole('encargado_360');
        // Revocar permiso a nivel de rol para esta transacción
        $role = Role::findByName('encargado_360');
        $role->revokePermissionTo('s360.enc360.assign');

        $this->actingAs($enc)
            ->postJson('/s360/enc360/psicologos', ['name' => 'Psico X', 'email' => 'psx@example.com'])
            ->assertForbidden();
    }
}

