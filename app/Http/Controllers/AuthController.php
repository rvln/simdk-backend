<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\VerifyEmailRequest;
use App\Http\Requests\ResendVerificationRequest;
use App\Services\UserService;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private UserService $userService) {}

    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // A realistic flow would trigger email inside an event.
        // For simplicity, we just generate the token via UserService here.
        $this->userService->generateVerificationToken($user);

        return response()->json([
            'status' => 'success',
            'message' => 'Registration successful. Please verify email.',
            'data' => $user
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if (!$user->email_verified_at) {
            return response()->json([
                'status' => 'error',
                'code' => 403,
                'message' => 'Email not verified.',
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'token' => $user->createToken('auth_token')->plainTextToken,
                'user' => $user,
            ]
        ]);
    }

    public function verifyEmail(VerifyEmailRequest $request)
    {
        // Simple mock mechanism. Normally would lookup token in DB.
        $user = \Illuminate\Support\Facades\Auth::user() ?? User::first(); 
        if(!$user) {
           return response()->json(['status' => 'error'], 404);
        }

        $this->userService->verifyEmail($user);

        return response()->json([
            'status' => 'success',
            'message' => 'Email verified successfully.'
        ]);
    }

    public function resendVerification(ResendVerificationRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if ($user && !$user->email_verified_at) {
            $this->userService->generateVerificationToken($user);
            return response()->json(['status' => 'success', 'message' => 'Verification email sent.']);
        }

        return response()->json(['status' => 'error', 'message' => 'User already verified or not found.'], 400);
    }
}
