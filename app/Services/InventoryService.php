<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\ItemDonation;
use App\Models\Inventory;
use App\Models\Distribution;
use App\Models\RejectedLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Enums\DonationStatusEnum;
use App\Enums\DonationTypeEnum;
use Symfony\Component\HttpKernel\Exception\HttpException;

class InventoryService
{
    /**
     * Phase 1: Pre-Submission logic with Smart Cart support.
     * Validates monthly limits per-item, then creates 1 Donation + N ItemDonation records atomically.
     *
     * UML Ref: Sequence Diagram §SD-4 — Phase 1: Pra-Submission
     *   1. checkMonthlyLimit(item_id, qty)
     *   2. If quota safe → insert(item_donation, status=pending_delivery)
     *
     * @param string|null $userId     Authenticated user UUID, null for guest donors.
     * @param array       $donorData  ['donorName', 'donorEmail', 'donorPhone']
     * @param array       $items      [['inventory_id' => UUID, 'qty' => int], ...]
     * @return Donation
     */
    public function submitPreSubmission(?string $userId, array $donorData, array $items): Donation
    {
        $monthlyLimit = (int) config('simdk.monthly_item_limit', 500);

        // Validate monthly limits for EACH item before entering the transaction
        foreach ($items as $item) {
            $currentMonthTotal = ItemDonation::where('inventory_id', $item['inventory_id'])
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('qty');

            if (($currentMonthTotal + $item['qty']) > $monthlyLimit) {
                $inventory = Inventory::find($item['inventory_id']);
                throw new HttpException(
                    422,
                    "Batas bulanan tercapai untuk item: " . ($inventory?->itemName ?? $item['inventory_id'])
                );
            }
        }

        return DB::transaction(function () use ($userId, $donorData, $items) {
            $paymentService = app(\App\Services\PaymentService::class);

            $donation = Donation::create([
                'user_id'       => $userId,
                'donorName'     => $donorData['donorName'],
                'donorEmail'    => $donorData['donorEmail'],
                'donorPhone'    => $donorData['donorPhone'],
                'type'          => DonationTypeEnum::BARANG->value,
                'status'        => DonationStatusEnum::PENDING_DELIVERY->value,
                'tracking_code' => $paymentService->generateTrackingCode(),
            ]);

            foreach ($items as $item) {
                $inventory = Inventory::findOrFail($item['inventory_id']);

                $donation->itemDonations()->create([
                    'inventory_id'      => $item['inventory_id'],
                    'itemName_snapshot' => $inventory->itemName,
                    'qty'               => $item['qty'],
                ]);
            }

            return $donation->load('itemDonations');
        });
    }

    /**
     * Phase 2: Check-in logic — accepts an item donation after physical inspection.
     * Atomically updates donation status to SUCCESS, increments inventory stock
     * with pessimistic locking, and triggers async notification.
     *
     * UML Ref: Sequence Diagram §SD-4 — Phase 2: Check-in (Accepted)
     *   1. BEGIN TRANSACTION
     *   2. update(item_donations, status=success)
     *   3. updateInventory(stock = stock + qty) via lockForUpdate()
     *   4. COMMIT
     *   5. [Output Kritis] sendAcceptanceNotification (async fire-and-forget)
     *
     * @param string $donationId  UUID of the donation to check in.
     * @return array              Serializable donation data.
     */
    public function checkInItem(string $donationId): array
    {
        $donation = Donation::with('itemDonations')->find($donationId);

        if (!$donation) {
            throw new HttpException(404, 'Donation not found.');
        }

        if ($donation->status->value !== DonationStatusEnum::PENDING_DELIVERY->value) {
            throw new HttpException(422, 'Only pending delivery donations can be checked in.');
        }

        $result = DB::transaction(function () use ($donation) {
            // Update donation status to SUCCESS
            $donation->update(['status' => DonationStatusEnum::SUCCESS->value]);

            // Increment stock for each item donation with pessimistic locking
            foreach ($donation->itemDonations as $item) {
                $inventory = Inventory::where('id', $item->inventory_id)
                    ->lockForUpdate()
                    ->first();

                if ($inventory) {
                    $inventory->increment('stock', $item->qty);
                }
            }

            return $donation->fresh()->toArray();
        });

        // Async external notification (fire-and-forget) — UML §SD-4 step 6
        $this->dispatchAcceptanceNotification($donation);

        return $result;
    }

    /**
     * Phase 2: Rejection logic — rejects an item donation after physical inspection.
     * Updates donation status to REJECTED and creates an auditable RejectedLog entry
     * WITH the donation_id FK as required by AGENTS.md §3.
     *
     * UML Ref: Sequence Diagram §SD-4 — Phase 2: Check-in (Rejected)
     *   [Tidak Layak] → InventoryService → Database: insert(rejected_logs)
     *
     * @param string $donationId  UUID of the donation to reject.
     * @param string $loggedBy    UUID of the Pengurus performing the rejection.
     * @param string $reason      Reason for rejection.
     * @return array              Serializable donation data.
     */
    public function rejectItem(string $donationId, string $loggedBy, string $reason): array
    {
        $donation = Donation::with('itemDonations')->find($donationId);

        if (!$donation) {
            throw new HttpException(404, 'Donation not found.');
        }

        if ($donation->status->value !== DonationStatusEnum::PENDING_DELIVERY->value) {
            throw new HttpException(422, 'Only pending delivery donations can be rejected.');
        }

        return DB::transaction(function () use ($donation, $loggedBy, $reason) {
            // Update donation status to REJECTED
            $donation->update(['status' => DonationStatusEnum::REJECTED->value]);

            // Create RejectedLog with donation_id FK for strict audit trail
            // AGENTS.md §3: rejection MUST be logged in RejectedLog WITH donation_id
            RejectedLog::create([
                'donation_id' => $donation->id,
                'itemName'    => $donation->itemDonations->first()?->itemName_snapshot ?? 'Unknown',
                'reason'      => $reason,
                'logged_by'   => $loggedBy,
            ]);

            return $donation->fresh()->toArray();
        });
    }

    /**
     * Record a distribution with atomic stock deduction.
     * Enforces auditability by requiring target_recipient and notes.
     *
     * UML Ref: Sequence Diagram §SD-7 — Distribution
     *   1. BEGIN TRANSACTION
     *   2. checkStock(item_id) via lockForUpdate()
     *   3. If current_stock < qty → ROLLBACK, throw InsufficientStock (HTTP 422)
     *   4. If valid, deduct stock + create Distribution record, COMMIT
     *
     * AGENTS.md §3: Distribution MUST explicitly record target_recipient and notes.
     *
     * @param array $data  ['inventory_id', 'user_id', 'qty', 'target_recipient', 'notes']
     * @return array       Serializable distribution data.
     */
    public function recordDistribution(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // Pessimistic locking on inventory row
            $inventory = Inventory::where('id', $data['inventory_id'])
                ->lockForUpdate()
                ->first();

            if (!$inventory) {
                throw new HttpException(404, 'Inventory item not found.');
            }

            if ($inventory->stock < $data['qty']) {
                throw new HttpException(422, 'Stok tidak mencukupi untuk distribusi.');
            }

            // Core atomic deduction
            $inventory->decrement('stock', $data['qty']);

            // Create distribution record WITH mandatory auditability fields
            $distribution = Distribution::create([
                'inventory_id'     => $data['inventory_id'],
                'user_id'          => $data['user_id'],
                'qty'              => $data['qty'],
                'target_recipient' => $data['target_recipient'],
                'notes'            => $data['notes'] ?? null,
                'distributed_at'   => now(),
            ]);

            return $distribution->toArray();
        });
    }

    /**
     * Retrieve all inventory items for the public Smart Cart dropdown.
     * Returns only the fields needed for the frontend selector.
     *
     * @return array  List of inventory items as arrays.
     */
    public function getPublicInventoryList(): array
    {
        return Inventory::select('id', 'itemName', 'category', 'stock', 'target_qty', 'unit', 'description')
            ->orderBy('category')
            ->orderBy('itemName')
            ->get()
            ->toArray();
    }

    /**
     * Dispatch an acceptance notification to the donor asynchronously (fire-and-forget).
     * Triggered after a successful item check-in.
     *
     * UML Ref: §SD-4 step 6 — sendAcceptanceNotification(donor_email, wa, donation_id)
     */
    private function dispatchAcceptanceNotification(Donation $donation): void
    {
        try {
            Mail::raw(
                "Donasi barang Anda dengan kode {$donation->tracking_code} telah diterima dan diverifikasi oleh Panti Asuhan Empanti. "
                . "Terima kasih atas kebaikan Anda!",
                function ($message) use ($donation) {
                    $message->to($donation->donorEmail)
                            ->subject('Donasi Barang Diterima - Empanti SIMDK');
                }
            );
        } catch (\Throwable $e) {
            // Fire-and-forget: log failure but do not block the check-in flow
            Log::warning("Failed to dispatch acceptance notification for donation {$donation->id}: " . $e->getMessage());
        }
    }
}
