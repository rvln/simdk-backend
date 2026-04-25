<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\ItemDonation;
use App\Models\Inventory;
use App\Models\Distribution;
use Illuminate\Support\Facades\DB;
use App\Enums\DonationStatusEnum;
use App\Enums\DonationTypeEnum;
use Exception;
use App\Services\PaymentService;

class InventoryService
{
    /**
     * Phase 1: Pre-Submission logic with Smart Cart support.
     * Validates monthly limits per-item, then creates 1 Donation + N ItemDonation records atomically.
     *
     * @param string|null $userId     Authenticated user UUID, null for guest donors.
     * @param array       $donorData  ['donorName', 'donorEmail', 'donorPhone']
     * @param array       $items      [['inventory_id' => UUID, 'qty' => int], ...]
     * @return Donation
     * @throws Exception
     */
    public function submitPreSubmission(?string $userId, array $donorData, array $items): Donation
    {
        $monthlyLimit = 500; // Hardcoded fallback or env driven

        // Validate monthly limits for EACH item before entering the transaction
        foreach ($items as $item) {
            $currentMonthTotal = ItemDonation::where('inventory_id', $item['inventory_id'])
                ->whereMonth('created_at', now()->month)
                ->sum('qty');

            if (($currentMonthTotal + $item['qty']) > $monthlyLimit) {
                $inventory = Inventory::find($item['inventory_id']);
                throw new Exception(
                    "Monthly limit exceeded for item: " . ($inventory?->itemName ?? $item['inventory_id'])
                );
            }
        }

        return DB::transaction(function () use ($userId, $donorData, $items) {
            $paymentService = app(PaymentService::class);

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
     * Phase 2: Check-in logic executing database transaction and updating stock safely.
     */
    public function checkInItem(Donation $donation)
    {
        if ($donation->status->value !== DonationStatusEnum::PENDING_DELIVERY->value) {
            throw new Exception("Only pending delivery donations can be checked in.");
        }

        return DB::transaction(function () use ($donation) {
            $donation->update(['status' => DonationStatusEnum::SUCCESS->value]);

            foreach ($donation->itemDonations as $item) {
                // Safeguard stock against race conditions during rapid concurrent checkups
                $inventory = Inventory::where('id', $item->inventory_id)->lockForUpdate()->first();
                if ($inventory) {
                    $inventory->increment('stock', $item->qty);
                }
            }

            // NOTE: Notify user code via Async Queue should trigger here
            
            return $donation;
        });
    }

    /**
     * Distribution logic with integrated Database Transactions for deduction integrity.
     */
    public function recordDistribution(array $data)
    {
        // $data contains: inventory_id, user_id, qty
        return DB::transaction(function () use ($data) {
            $inventory = Inventory::where('id', $data['inventory_id'])->lockForUpdate()->first();
            
            if (!$inventory || $inventory->stock < $data['qty']) {
                throw new Exception("Insufficient stock available for distribution.");
            }

            // Core atomic deduction
            $inventory->decrement('stock', $data['qty']);

            $distribution = Distribution::create([
                'inventory_id'   => $data['inventory_id'],
                'user_id'        => $data['user_id'],
                'qty'            => $data['qty'],
                'distributed_at' => now(),
            ]);

            return $distribution;
        });
    }
}
