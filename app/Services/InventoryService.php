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
     * Phase 1: Pre-Submission logic strictly validating monthly limits and saving as pending.
     */
    public function submitPreSubmission(array $data)
    {
        // $data contains: donorName, donorEmail, donorPhone, inventory_id, qty, itemName_snapshot
        $monthlyLimit = 500; // Hardcoded fallback or env driven
        
        $currentMonthTotal = ItemDonation::where('inventory_id', $data['inventory_id'])
            ->whereMonth('created_at', now()->month)
            ->sum('qty');

        if (($currentMonthTotal + $data['qty']) > $monthlyLimit) {
            throw new Exception("Monthly limit exceeded for this item.");
        }

        return DB::transaction(function () use ($data) {
            $paymentService = app(PaymentService::class);
            
            $donation = Donation::create([
                'donorName'     => $data['donorName'],
                'donorEmail'    => $data['donorEmail'],
                'donorPhone'    => $data['donorPhone'],
                'type'          => DonationTypeEnum::BARANG->value,
                'status'        => DonationStatusEnum::PENDING_DELIVERY->value,
                'tracking_code' => $paymentService->generateTrackingCode(),
            ]);

            $donation->itemDonations()->create([
                'inventory_id'      => $data['inventory_id'],
                'itemName_snapshot' => $data['itemName_snapshot'],
                'qty'               => $data['qty']
            ]);

            return $donation;
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
