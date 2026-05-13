<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\SocialAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    protected SocialAuthService $socialAuthService;

    public function __construct(SocialAuthService $socialAuthService)
    {
        $this->socialAuthService = $socialAuthService;
    }

    /**
     * Redirect to Google authentication page.
     */
    public function redirectToGoogle(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle callback from Google.
     */
    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        // Jika ingin validasi request, bisa gunakan Form Request khusus di sini
        // $request->validate([...]);

        try {
            // Panggil Service untuk menangani seluruh logika bisnis
            $user = $this->socialAuthService->handleGoogleCallback();

            return redirect()->intended('/dashboard')->with('success', 'Login berhasil!');
        } catch (\Exception $e) {
            // Tangani jika user menolak atau terjadi error lain
            return redirect()->route('login')->with('error', 'Autentikasi Google gagal. Silakan coba lagi.');
        }
    }
}