<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\VerifyEmailRequest;
use App\Http\Requests\ResendVerificationRequest;
use App\Services\UserService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthController extends Controller
{
    public function __construct(private UserService $userService) {}

    /**
     * POST /register
     * Delegates user creation entirely to UserService.
     */
    public function register(RegisterRequest $request)
    {
        $user = $this->userService->registerUser($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Registration successful. Please verify email.',
            'data' => $user
        ], 201);
    }

    /**
     * POST /login
     * Delegates credential validation and email_verified_at enforcement to UserService.
     * Returns 403 if email is not verified (per AGENTS.md Authentication State Constraint).
     */
    public function login(LoginRequest $request)
    {
        try {
            $result = $this->userService->attemptLogin(
                $request->email,
                $request->password,
            );

            return response()->json([
                'status' => 'success',
                'data' => $result,
            ]);
        } catch (HttpException $e) {
            return response()->json([
                'status' => 'error',
                'code' => $e->getStatusCode(),
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }

    /**
     * POST /verify-email
     * Delegates token-based verification to UserService.
     * No mock data — evaluates the cryptographic token string from the request payload.
     */
    public function verifyEmail(VerifyEmailRequest $request)
    {
        try {
            $this->userService->verifyEmailByToken($request->token);

            return response()->json([
                'status' => 'success',
                'message' => 'Email verified successfully.'
            ]);
        } catch (HttpException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }

    /**
     * POST /resend-verification
     * Delegates the 3-step resend flow (invalidate → regenerate → dispatch) to UserService.
     */
    public function resendVerification(ResendVerificationRequest $request)
    {
        try {
            $this->userService->resendVerificationEmail($request->email);

            return response()->json([
                'status' => 'success',
                'message' => 'Verification email sent.',
            ]);
        } catch (HttpException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }
}
