<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Actions\Sale\GenerateSaleCarneAction;
use App\Models\Client;
use App\Models\Development;
use App\Models\Installment;
use App\Models\Lot;
use App\Models\Sale;
use App\Services\EfiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GenerateSaleCarneActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_uses_tomorrow_when_first_unpaid_installment_is_overdue(): void
    {
        Carbon::setTestNow('2026-05-29 12:00:00');

        $sale = $this->createSaleWithInstallments(
            firstDueDate: '2026-05-25',
            installmentsCount: 2,
        );

        $efi = Mockery::mock(EfiService::class);
        $efi->shouldReceive('createCarne')
            ->once()
            ->withArgs(function (
                float $value,
                int $count,
                string $firstDueDate,
            ) use ($sale): bool {
                return $value === (float) $sale->installment_value
                    && $count === 2
                    && $firstDueDate === '2026-05-30';
            })
            ->andReturn([
                'carnet_id' => 123,
                'status' => 'active',
                'link' => 'https://example.com/carne',
                'pdf_carnet' => 'https://example.com/carne.pdf',
                'pdf_cover' => null,
                'charges' => [
                    [
                        'charge_id' => 1,
                        'parcel' => 1,
                        'status' => 'waiting',
                        'value' => (int) $sale->installment_value,
                        'expire_at' => '2026-05-30',
                        'pdf' => null,
                        'barcode' => '123',
                    ],
                    [
                        'charge_id' => 2,
                        'parcel' => 2,
                        'status' => 'waiting',
                        'value' => (int) $sale->installment_value,
                        'expire_at' => '2026-06-30',
                        'pdf' => null,
                        'barcode' => '456',
                    ],
                ],
            ]);

        $action = new GenerateSaleCarneAction($efi);
        $result = $action->execute($sale);

        $this->assertSame('2026-05-30', $result['first_due_date']);
        $this->assertTrue($result['adjusted_from_scheduled']);
        $this->assertSame(123, $sale->fresh()->efi_carnet_id);
    }

    public function test_uses_requested_first_due_date_when_valid(): void
    {
        Carbon::setTestNow('2026-05-29 12:00:00');

        $sale = $this->createSaleWithInstallments(
            firstDueDate: '2026-06-10',
            installmentsCount: 1,
        );

        $efi = Mockery::mock(EfiService::class);
        $efi->shouldReceive('createCarne')
            ->once()
            ->withArgs(fn ($value, $count, $firstDueDate): bool => $firstDueDate === '2026-06-15')
            ->andReturn([
                'carnet_id' => 456,
                'status' => 'active',
                'link' => 'https://example.com/carne',
                'pdf_carnet' => 'https://example.com/carne.pdf',
                'pdf_cover' => null,
                'charges' => [
                    [
                        'charge_id' => 9,
                        'parcel' => 1,
                        'status' => 'waiting',
                        'value' => (int) $sale->installment_value,
                        'expire_at' => '2026-06-15',
                        'pdf' => null,
                        'barcode' => '789',
                    ],
                ],
            ]);

        $action = new GenerateSaleCarneAction($efi);
        $result = $action->execute($sale, '2026-06-15');

        $this->assertSame('2026-06-15', $result['first_due_date']);
        $this->assertFalse($result['adjusted_from_scheduled']);
    }

    private function createSaleWithInstallments(string $firstDueDate, int $installmentsCount): Sale
    {
        $client = Client::query()->create([
            'name' => 'Cliente Teste',
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

        $sale = Sale::withoutEvents(function () use ($lot, $client, $firstDueDate, $installmentsCount): Sale {
            return Sale::query()->create([
                'lot_id' => $lot->id,
                'client_id' => $client->id,
                'sale_date' => '2026-05-01',
                'total_value' => 7000000,
                'down_payment' => 1400000,
                'financed_value' => 5600000,
                'installments_count' => $installmentsCount,
                'installment_value' => 233333,
                'first_due_date' => $firstDueDate,
                'payment_day' => 25,
                'status' => 'active',
            ]);
        });

        $dueDate = Carbon::parse($firstDueDate);

        for ($number = 1; $number <= $installmentsCount; $number++) {
            Installment::query()->create([
                'sale_id' => $sale->id,
                'type' => Installment::TYPE_FINANCING,
                'number' => $number,
                'due_date' => $dueDate->toDateString(),
                'value' => 233333,
                'status' => Installment::STATUS_PENDING,
            ]);

            $dueDate->addMonth();
        }

        return $sale->fresh(['client', 'lot.development', 'installments']);
    }
}
