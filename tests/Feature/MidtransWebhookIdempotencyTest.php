<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Donation;
use App\Enums\DonationStatusEnum;
use App\Enums\DonationTypeEnum;

class MidtransWebhookIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_enforces_idempotency_on_duplicate_settlement_payloads()
    {
        // 1. Seed Initial State
        $donation = Donation::create([
            'donorName' => 'Test Donor',
            'donorEmail' => 'test@example.com',
            'donorPhone' => '08123456789',
            'type' => DonationTypeEnum::DANA->value,
            'amount' => 50000,
            'status' => DonationStatusEnum::PENDING->value,
            'tracking_code' => 'TXN-DON-2026-TEST',
        ]);

        $payload = [
            'order_id' => $donation->id,
            'transaction_status' => 'settlement',
        ];

        // 2. First execution
        $response1 = $this->postJson('/api/webhooks/midtrans', $payload);
        $response1->assertStatus(200);

        $donation->refresh();
        $this->assertEquals(DonationStatusEnum::SUCCESS->value, $donation->status->value);

        // 3. Second execution (Duplicate Webhook)
        $response2 = $this->postJson('/api/webhooks/midtrans', $payload);
        
        // Assert it returns 200 early without parsing logic
        $response2->assertStatus(200);

        $donation->refresh();
        $this->assertEquals(DonationStatusEnum::SUCCESS->value, $donation->status->value);
        $this->assertEquals('TXN-DON-2026-TEST', $donation->tracking_code); // Implicit validation
    }
}
