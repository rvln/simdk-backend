<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthService
{
    /**
     * Handle Google authentication callback.
     *
     * @return \App\Models\User
     */
    public function handleGoogleCallback(): User
    {
        // 1. Dapatkan data user dari Google
        $googleUser = Socialite::driver('google')->user();

        // 2. Bungkus operasi database dalam transaksi
        return DB::transaction(function () use ($googleUser) {
            // 3. Cari user berdasarkan email atau google_id
            $user = User::where('email', $googleUser->getEmail())
                        ->orWhere('google_id', $googleUser->getId())
                        ->first();

            if ($user) {
                // Jika user sudah ada, perbarui google_id jika kosong
                if (is_null($user->google_id)) {
                    $user->update([
                        'google_id' => $googleUser->getId(),
                    ]);
                }
            } else {
                // Jika user benar-benar baru, buat akun
                $user = User::create([
                    'name'              => $googleUser->getName(),
                    'email'             => $googleUser->getEmail(),
                    'google_id'         => $googleUser->getId(),
                    'password'          => Hash::make(Str::random(24)),
                    'email_verified_at' => now(),
                ]);
            }

            // 4. Login pengguna
            Auth::login($user, true);

            return $user;
        });
    }
}