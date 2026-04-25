<?php

namespace App\Http\Controllers;

use App\Models\Donation;

class TrackingController extends Controller
{
    public function trackDonation($tracking_code)
    {
        $donation = Donation::where('tracking_code', $tracking_code)->first();

        if (!$donation) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        // Return Data limited map explicitly as mapped out within NFR-04
        return response()->json([
            'status' => 'success',
            'data' => [
                'tracking_code' => $donation->tracking_code,
                'transaction_date' => $donation->created_at,
                'payment_status' => $donation->status,
                'type' => $donation->type,
                // A complete system would pull Distribution state if applicable.
                'distribution_status' => 'pending' 
            ]
        ]);
    }
}
