<?php

namespace App\Services;

use App\Models\Donation;
use App\Enums\DonationStatusEnum;
use App\Enums\DonationTypeEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PaymentService
{
    /**
     * Initiate a financial (DANA) donation.
     * Creates a Donation record with status=PENDING and type=DANA.
     * tracking_code is NOT generated here — only upon confirmed SUCCESS via webhook.
     *
     * UML Ref: Sequence Diagram §SD-3 — DonationController → PaymentService: initiate
     *   PaymentService → Midtrans API: getCheckoutUrl()
     *
     * @param string|null $userId  Authenticated user UUID (nullable for guest donors).
     * @param array $donorData     ['donorName', 'donorEmail', 'donorPhone']
     * @param float $amount        Donation amount (minimum enforced by FormRequest).
     * @return array               Serializable response with transaction_id and checkout_url.
     */
    public function initiateDonation(?string $userId, array $donorData, float $amount): array
    {
        $donation = Donation::create([
            'user_id'    => $userId,
            'donorName'  => $donorData['donorName'],
            'donorEmail' => $donorData['donorEmail'],
            'donorPhone' => $donorData['donorPhone'],
            'type'       => DonationTypeEnum::DANA->value,
            'amount'     => $amount,
            'status'     => DonationStatusEnum::PENDING->value,
        ]);

        // In production, call Midtrans Snap API here:
        // $checkoutUrl = $this->midtransClient->createTransaction($donation->id, $amount);
        $checkoutUrl = 'https://app.sandbox.midtrans.com/snap/v3/redacted';

        return [
            'transaction_id' => $donation->id,
            'checkout_url'   => $checkoutUrl,
        ];
    }

    /**
     * Process a Midtrans webhook callback with strict Idempotency Guard.
     *
     * UML Ref: Sequence Diagram §SD-3 — [Idempotency Guard]
     *   1. Verify signature (placeholder for production)
     *   2. Lookup order by order_id
     *   3. If current_status == final (success/failed/expired) → discard, return true
     *   4. If not final, evaluate transaction_status and update atomically
     *   5. On 'settlement'/'capture' → DB::transaction { status=SUCCESS + generate tracking_code }
     *
     * AGENTS.md §3: Webhook Idempotency — if order_id is already marked as final,
     *   return HTTP 200 OK immediately without modifying the database.
     *
     * @param array $payload  Raw webhook payload from Midtrans.
     * @return bool           True if processed successfully (including idempotent hits).
     */
    public function processWebhook(array $payload): bool
    {
        $orderId = $payload['order_id'] ?? null;
        $transactionStatus = $payload['transaction_status'] ?? null;

        if (!$orderId || !$transactionStatus) {
            throw new HttpException(400, 'Missing required webhook fields.');
        }

        // Production: verify Midtrans signature hash
        // $this->verifySignature($payload);

        $donation = Donation::find($orderId);

        if (!$donation) {
            throw new HttpException(404, 'Donation not found.');
        }

        // Idempotency Guard: if the donation is already in a final state, discard silently
        $finalStatuses = [
            DonationStatusEnum::SUCCESS->value,
            DonationStatusEnum::FAILED->value,
            DonationStatusEnum::EXPIRED->value,
        ];

        if (in_array($donation->status->value, $finalStatuses)) {
            Log::info("Webhook idempotency hit for order_id={$orderId}. Status={$donation->status->value}. Discarded.");
            return true;
        }

        // Process based on Midtrans transaction_status
        if ($transactionStatus === 'settlement' || $transactionStatus === 'capture') {
            // Atomic Operation: status update + tracking_code generation must be indivisible
            // UML Ref: §SD-3 step 5 — BEGIN, generateCode(), update status+tracking_code, COMMIT
            DB::transaction(function () use ($donation) {
                $donation->update([
                    'status'        => DonationStatusEnum::SUCCESS->value,
                    'tracking_code' => $this->generateTrackingCode(),
                ]);
            });
        } elseif ($transactionStatus === 'expire') {
            $donation->update(['status' => DonationStatusEnum::EXPIRED->value]);
        } elseif ($transactionStatus === 'cancel' || $transactionStatus === 'deny') {
            $donation->update(['status' => DonationStatusEnum::FAILED->value]);
        }

        return true;
    }

    /**
     * Retrieve limited tracking data for a public tracking query.
     * Returns ONLY: tracking_code, transaction_date, payment_status, type, distribution_status.
     * MUST NOT expose internal operational data or recipient identities.
     *
     * UML Ref: Sequence Diagram §SD-5 — TrackingController → Database: findDonation(tracking_code)
     * SRS NFR-04: Public Tracking data limitation.
     *
     * @param string $trackingCode  The public-facing tracking code.
     * @return array                Limited DTO for public consumption.
     */
    public function getPublicTrackingData(string $trackingCode): array
    {
        $donation = Donation::where('tracking_code', $trackingCode)->first();

        if (!$donation) {
            throw new HttpException(404, 'Data tidak ditemukan');
        }

        // Determine distribution status by checking if any related inventory items
        // have been distributed (via the donation's itemDonations → inventory → distributions chain).
        // For DANA type, distribution_status is derived from the donation status itself.
        $distributionStatus = $this->resolveDistributionStatus($donation);

        return [
            'tracking_code'       => $donation->tracking_code,
            'transaction_date'    => $donation->created_at,
            'payment_status'      => $donation->status,
            'type'                => $donation->type,
            'distribution_status' => $distributionStatus,
        ];
    }

    /**
     * Generates a unique, immutable tracking code.
     * Format: TXN-DON-YYYY-XXXX
     *
     * UML Ref: §SD-3 step 5 — PaymentService: generateCode() → string(tracking_code)
     * AGENTS.md §3: Public Tracking — every successful donation must generate a unique, immutable tracking_code.
     *
     * @return string
     */
    public function generateTrackingCode(): string
    {
        do {
            $uuidFragment = strtoupper(substr(uniqid(), -4));
            $year = now()->format('Y');
            $code = "TXN-DON-{$year}-{$uuidFragment}";
        } while (Donation::where('tracking_code', $code)->exists());

        return $code;
    }

    /**
     * Resolve the distribution status for a donation.
     * For DANA donations, status mirrors the payment status.
     * For BARANG donations, checks if item donations have been distributed.
     *
     * @param Donation $donation
     * @return string
     */
    private function resolveDistributionStatus(Donation $donation): string
    {
        if ($donation->type->value === DonationTypeEnum::DANA->value) {
            return $donation->status->value === DonationStatusEnum::SUCCESS->value
                ? 'allocated'
                : 'pending';
        }

        // For BARANG: check if any item donations exist and if their inventory has distributions
        $itemDonations = $donation->itemDonations;

        if ($itemDonations->isEmpty()) {
            return 'pending';
        }

        // If all item donations have matching distributions, consider it distributed
        $allDistributed = $itemDonations->every(function ($item) {
            return $item->inventory &&
                   $item->inventory->distributions &&
                   $item->inventory->distributions->isNotEmpty();
        });

        return $allDistributed ? 'distributed' : 'pending';
    }
}
