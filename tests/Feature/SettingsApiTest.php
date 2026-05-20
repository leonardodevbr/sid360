<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        $this->manager = User::factory()->create([
            'email' => 'manager-settings@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->manager->assignRole('manager');

        // Seed default settings
        $this->seed(SettingSeeder::class);
    }

    private function seedRolesAndPermissions(): void
    {
        $permissions = [
            'settings.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $managerRole->syncPermissions($permissions);
    }

    public function test_settings_require_authentication(): void
    {
        $response = $this->getJson('/api/settings');

        $response->assertStatus(401);
    }

    public function test_settings_require_permission(): void
    {
        $user = User::factory()->create([
            'email' => 'no-permission@test.com',
            'password' => Hash::make('password'),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/settings');

        $response->assertStatus(403);
    }

    public function test_can_list_settings_grouped_by_group(): void
    {
        $token = $this->manager->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/settings');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'general',
            'payment',
        ]);

        $general = $response->json('general');
        $payment = $response->json('payment');

        $this->assertTrue(
            collect($general)->contains(fn (array $setting) => $setting['key'] === 'store_name' && $setting['value'] === 'Adonai Boutique')
        );

        $this->assertTrue(
            collect($payment)->contains(fn (array $setting) => $setting['key'] === 'payment_gateway' && $setting['value'] === 'pix_dev')
        );
    }

    public function test_can_update_multiple_settings(): void
    {
        $token = $this->manager->createToken('test-token')->plainTextToken;

        $payload = [
            'settings' => [
                [
                    'key' => 'store_name',
                    'value' => 'Adonai Boutique Centro',
                    'group' => 'general',
                    'type' => 'string',
                ],
                [
                    'key' => 'enable_cash_register',
                    'value' => false,
                    'group' => 'general',
                    'type' => 'boolean',
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/settings', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Settings updated successfully.',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'store_name',
            'group' => 'general',
            'type' => 'string',
            'value' => 'Adonai Boutique Centro',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'enable_cash_register',
            'group' => 'general',
            'type' => 'boolean',
            'value' => '0',
        ]);
    }
}

