<?php

namespace App\Services;

use App\Models\Donation;

class PaymentService
{
    /**
     * Secures donation immutability with a unique tracking code per the BRS logic.
     */
    public function generateTrackingCode(): string
    {
        do {
            $uuidFragment = strtoupper(substr(uniqid(), -4));
            $year = now()->format('Y');
            
            // Expected Output Format: TXN-DON-YYYY-XXXX
            $code = "TXN-DON-{$year}-{$uuidFragment}";
            
        } while (Donation::where('tracking_code', $code)->exists());

        return $code;
    }
}
