<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class UserService
{
    /**
     * Generates a secure randomized token for email verification
     */
    public function generateVerificationToken(User $user): string
    {
        // Leveraging robust HMAC to safely encode tokens against Laravel's App Key
        return hash_hmac('sha256', Str::random(40), config('app.key'));
    }

    /**
     * Secures and activates the user account explicitly.
     */
    public function verifyEmail(User $user)
    {
        $user->update([
            'email_verified_at' => now()
        ]);
        
        return $user;
    }
}
