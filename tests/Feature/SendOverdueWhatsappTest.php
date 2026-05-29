<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Development;
use App\Models\Installment;
use App\Models\Lot;
use App\Models\Sale;
use App\Models\User;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SendOverdueWhatsappTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'sales.edit', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'sales.view', 'guard_name' => 'web']);
    }

    public function test_manual_overdue_whatsapp_resends_even_if_already_sent(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('sales.edit');

        $client = Client::query()->create([
            'name' => 'Cliente Teste',
            'cpf' => '52998224725',
            'phone' => '74999998888',
        ]);

        $development = Development::query()->create([
            'name' => 'Residencial Teste',
            'slug' => 'residencial-teste',
            'status' => 'active',
        ]);

        $lot = Lot::query()->create([
            'development_id' => $development->id,
            'number' => '01',
            'block' => 'A',
            'area' => 300,
            'price' => 5000000,
            'status' => 'sold',
        ]);

        $sale = Sale::withoutEvents(function () use ($lot, $client): Sale {
            return Sale::query()->create([
                'lot_id' => $lot->id,
                'client_id' => $client->id,
                'sale_date' => now()->toDateString(),
                'total_value' => 5000000,
                'down_payment' => 0,
                'financed_value' => 5000000,
                'installments_count' => 1,
                'installment_value' => 5000000,
                'first_due_date' => now()->subMonth()->toDateString(),
                'payment_day' => 10,
                'status' => 'active',
            ]);
        });

        Installment::query()->create([
            'sale_id' => $sale->id,
            'type' => Installment::TYPE_FINANCING,
            'number' => 1,
            'due_date' => now()->subDays(10)->toDateString(),
            'value' => 5000000,
            'status' => Installment::STATUS_PENDING,
            'whatsapp_overdue_sent_at' => now()->subDay(),
        ]);

        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('sendListAndRecord')->once()->andReturn(true);
        $this->app->instance(WhatsappService::class, $whatsapp);

        $response = $this->actingAs($user)
            ->postJson("/api/sales/{$sale->id}/whatsapp/overdue");

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('overdue_count', 1);
    }
}
