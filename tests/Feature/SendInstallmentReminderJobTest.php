<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendInstallmentReminderJob;
use App\Models\Client;
use App\Models\Development;
use App\Models\Installment;
use App\Models\InstallmentInteraction;
use App\Models\Lot;
use App\Models\Sale;
use App\Models\Setting;
use App\Services\WhatsappService;
use App\Support\WhatsappReminderButtons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SendInstallmentReminderJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sends_reminder_with_quick_reply_buttons(): void
    {
        Setting::query()->create([
            'key' => 'whatsapp_notifications_enabled',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'whatsapp',
        ]);
        Setting::query()->create([
            'key' => 'whatsapp_reminder_enabled',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'whatsapp',
        ]);

        $client = Client::query()->create([
            'name' => 'Cliente Teste',
            'cpf' => '52998224725',
            'phone' => '74988230151',
            'whatsapp_status' => Client::WHATSAPP_STATUS_CONFIRMED,
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
                'first_due_date' => now()->addDays(3)->toDateString(),
                'payment_day' => 10,
                'status' => 'active',
            ]);
        });

        $installment = Installment::query()->create([
            'sale_id' => $sale->id,
            'type' => Installment::TYPE_FINANCING,
            'number' => 1,
            'due_date' => now()->addDays(3)->toDateString(),
            'value' => 5000000,
            'status' => Installment::STATUS_PENDING,
        ]);

        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('interpolate')->andReturn('Lembrete de parcela');
        $whatsapp->shouldReceive('sendQuickReplyButtonsAndRecord')
            ->once()
            ->withArgs(function (
                string $phone,
                string $message,
                array $buttons,
                string $type,
                int $installmentId,
            ): bool {
                return str_contains($phone, '74988230151')
                    && $message === 'Lembrete de parcela'
                    && $buttons === WhatsappReminderButtons::buttons()
                    && $type === InstallmentInteraction::TYPE_REMINDER
                    && $installmentId > 0;
            })
            ->andReturn(true);
        $this->app->instance(WhatsappService::class, $whatsapp);

        SendInstallmentReminderJob::dispatchSync($installment);

        $installment->refresh();
        $this->assertNotNull($installment->whatsapp_reminder_sent_at);
    }

    public function test_falls_back_to_text_when_buttons_send_fails(): void
    {
        Setting::query()->create([
            'key' => 'whatsapp_notifications_enabled',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'whatsapp',
        ]);
        Setting::query()->create([
            'key' => 'whatsapp_reminder_enabled',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'whatsapp',
        ]);

        $client = Client::query()->create([
            'name' => 'Cliente Teste',
            'cpf' => '52998224725',
            'phone' => '74988230151',
            'whatsapp_status' => Client::WHATSAPP_STATUS_CONFIRMED,
        ]);

        $development = Development::query()->create([
            'name' => 'Residencial Teste',
            'slug' => 'residencial-teste-2',
            'status' => 'active',
        ]);

        $lot = Lot::query()->create([
            'development_id' => $development->id,
            'number' => '02',
            'block' => 'B',
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
                'first_due_date' => now()->addDays(3)->toDateString(),
                'payment_day' => 10,
                'status' => 'active',
            ]);
        });

        $installment = Installment::query()->create([
            'sale_id' => $sale->id,
            'type' => Installment::TYPE_FINANCING,
            'number' => 2,
            'due_date' => now()->addDays(3)->toDateString(),
            'value' => 5000000,
            'status' => Installment::STATUS_PENDING,
        ]);

        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('interpolate')->andReturn('Lembrete texto');
        $whatsapp->shouldReceive('sendQuickReplyButtonsAndRecord')->once()->andReturn(false);
        $whatsapp->shouldReceive('sendAndRecord')->once()->andReturn(true);
        $this->app->instance(WhatsappService::class, $whatsapp);

        SendInstallmentReminderJob::dispatchSync($installment);

        $installment->refresh();
        $this->assertNotNull($installment->whatsapp_reminder_sent_at);
    }
}
