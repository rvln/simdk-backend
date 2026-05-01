<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Donation;
use App\Models\Inventory;
use App\Enums\DonationTypeEnum;
use App\Enums\DonationStatusEnum;
use Illuminate\Support\Str;

class PublicDonationController extends Controller
{
    /**
     * Store a newly created item donation from the public form.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'donorName' => 'required|string|max:255',
            'donorPhone' => 'required|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|string',
            'items.*.name' => 'required_if:items.*.id,MANUAL|string|max:255',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        $trackingCode = 'TXN-DON-' . date('Ymd') . '-' . strtoupper(Str::random(6));

        $donation = DB::transaction(function () use ($validated, $trackingCode, $request) {
            $donation = Donation::create([
                'tracking_code' => $trackingCode,
                'donorName'     => $validated['donorName'],
                'donorEmail'    => $request->input('donorEmail', null),
                'donorPhone'    => $validated['donorPhone'],
                'type'          => DonationTypeEnum::BARANG->value,
                'status'        => DonationStatusEnum::PENDING_DELIVERY->value,
            ]);

            foreach ($validated['items'] as $item) {
                if ($item['id'] === 'MANUAL') {
                    $donation->itemDonations()->create([
                        'inventory_id'      => null,
                        'item_name'         => $item['name'],
                        'itemName_snapshot' => $item['name'],
                        'qty'               => $item['qty'],
                    ]);
                } else {
                    $inventory = Inventory::findOrFail($item['id']);
                    $donation->itemDonations()->create([
                        'inventory_id'      => $item['id'],
                        'item_name'         => $inventory->itemName,
                        'itemName_snapshot' => $inventory->itemName,
                        'qty'               => $item['qty'],
                    ]);
                }
            }

            return $donation;
        });

        return response()->json([
            'status' => 'success',
            'tracking_code' => $donation->tracking_code,
        ], 201);
    }

    /**
     * Retrieve a donation by its tracking code for public hydration.
     */
    public function show($tracking_code)
    {
        $donation = Donation::with('itemDonations')
            ->where('tracking_code', $tracking_code)
            ->first();

        if (!$donation) {
            return response()->json(['message' => 'Resi tidak ditemukan.'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $donation
        ]);
    }
}
