<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Donation;
use App\Enums\DonationStatusEnum;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handleMidtransWebhook(Request $request)
    {
        // Typically, verify Signature Key first
        // $signature = $request->header('X-Midtrans-Signature');
        
        $order_id = $request->input('order_id');
        $transaction_status = $request->input('transaction_status');

        $donation = Donation::find($order_id);

        if (!$donation) {
            return response()->json(['status' => 'error', 'message' => 'Not Found'], 404);
        }

        // Idempotency check 
        $finalStatuses = [
            DonationStatusEnum::SUCCESS->value,
            DonationStatusEnum::FAILED->value,
            DonationStatusEnum::EXPIRED->value
        ];

        if (in_array($donation->status->value, $finalStatuses)) {
            Log::info("Idempotency hit for {$order_id}. Ignored.");
            return response()->json(['status' => 'success'], 200);
        }

        // Processing logic
        if ($transaction_status === 'settlement' || $transaction_status === 'capture') {
            $donation->update(['status' => DonationStatusEnum::SUCCESS->value]);
        } elseif ($transaction_status === 'expire') {
            $donation->update(['status' => DonationStatusEnum::EXPIRED->value]);
        } elseif ($transaction_status === 'cancel' || $transaction_status === 'deny') {
            $donation->update(['status' => DonationStatusEnum::FAILED->value]);
        }

        return response()->json(['status' => 'success']);
    }
}
