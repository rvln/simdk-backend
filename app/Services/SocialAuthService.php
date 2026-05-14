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

        // 2. Gunakan transaksi untuk menjaga integritas data
        return DB::transaction(function () use ($googleUser) {
            // 3. Cari user berdasarkan email
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                // Jika user ada tapi belum punya google_id, hubungkan akunnya
                if (empty($user->google_id)) {
                    $user->update([
                        'google_id' => $googleUser->getId(),
                    ]);
                }
            } else {
                // Jika user benar-benar baru, buat akun baru
                $user = User::create([
                    'name'              => $googleUser->getName(),
                    'email'             => $googleUser->getEmail(),
                    'google_id'         => $googleUser->getId(),
                    'password'          => Hash::make(Str::random(32)),
                    'email_verified_at' => now(), // User dari Google otomatis terverifikasi
                    'role'              => \App\Enums\RoleEnum::PENGUNJUNG,
                ]);
            }

            return $user;
        });
    }
}