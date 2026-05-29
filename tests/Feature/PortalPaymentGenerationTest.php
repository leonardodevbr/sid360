<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Portal\AuthenticatePortalAction;
use App\Models\Client;
use App\Models\Development;
use App\Models\Installment;
use App\Models\Lot;
use App\Models\Sale;
use App\Services\EfiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class PortalPaymentGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_portal_client_can_generate_pix_for_own_installment(): void
    {
        [$client, $installment] = $this->createClientWithInstallment();
        $token = $this->portalTokenFor($client);

        $efi = Mockery::mock(EfiService::class);
        $efi->shouldReceive('createPixCharge')->once()->andReturn([
            'txid' => 'abc123',
            'loc_id' => 99,
            'pix_copia_cola' => '000201',
        ]);
        $efi->shouldReceive('getPixQrCode')->once()->andReturn([
            'image' => 'base64image',
            'copy_paste' => '000201',
        ]);
        $this->app->instance(EfiService::class, $efi);

        $response = $this->withToken($token)
            ->postJson("/api/portal/installments/{$installment->id}/pix");

        $response->assertOk()
            ->assertJsonPath('pix_copia_cola', '000201')
            ->assertJsonPath('installment.id', $installment->id);

        $this->assertSame('000201', $installment->fresh()->efi_pix_copia_cola);
    }

    public function test_portal_client_cannot_generate_pix_for_foreign_installment(): void
    {
        [, $installment] = $this->createClientWithInstallment();

        $otherClient = Client::query()->create([
            'name' => 'Outro Cliente',
            'cpf' => '39053344705',
            'phone' => '74999990000',
        ]);

        $token = $this->portalTokenFor($otherClient);

        $this->withToken($token)
            ->postJson("/api/portal/installments/{$installment->id}/pix")
            ->assertForbidden();
    }

    /**
     * @return array{0: Client, 1: Installment}
     */
    private function createClientWithInstallment(): array
    {
        $client = Client::query()->create([
            'name' => 'Cliente Portal',
            'cpf' => '52998224725',
            'phone' => '74988230151',
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
            'price' => 7000000,
            'status' => 'sold',
        ]);

        $sale = Sale::withoutEvents(function () use ($lot, $client): Sale {
            return Sale::query()->create([
                'lot_id' => $lot->id,
                'client_id' => $client->id,
                'sale_date' => '2026-05-01',
                'total_value' => 7000000,
                'down_payment' => 1400000,
                'financed_value' => 5600000,
                'installments_count' => 1,
                'installment_value' => 233333,
                'first_due_date' => '2026-06-10',
                'payment_day' => 10,
                'status' => 'active',
            ]);
        });

        $installment = Installment::query()->create([
            'sale_id' => $sale->id,
            'type' => Installment::TYPE_FINANCING,
            'number' => 1,
            'due_date' => '2026-06-10',
            'value' => 233333,
            'status' => Installment::STATUS_PENDING,
        ]);

        return [$client, $installment];
    }

    private function portalTokenFor(Client $client): string
    {
        $token = 'portal-test-token';
        Cache::put("portal:token:{$token}", $client->id, now()->addHour());

        return $token;
    }
}
