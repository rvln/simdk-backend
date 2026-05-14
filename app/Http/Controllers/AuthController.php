<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\VerifyEmailRequest;
use App\Http\Requests\ResendVerificationRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Services\UserService;
use Illuminate\Http\Request;
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

    /**
     * POST /logout
     * Revokes the current Sanctum Personal Access Token, fully invalidating the session.
     * Returns HTTP 204 No Content per REST convention for delete-like operations.
     *
     * Security Ref: SRS NFR-02 — Token must be completely destroyed upon logout.
     * Audit Ref: G-01 — Sanctum Token Revocation hotfix.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent(); // HTTP 204
    }

    /**
     * DELETE /user/account
     * Permanently deletes the authenticated user's account and ALL owned history
     * (visits, donations, item_donations, visit_reports, tokens).
     *
     * Delegates entirely to UserService::deleteAccount() which wraps the operation
     * in a DB transaction. Returns HTTP 204 on success.
     *
     * AGENTS.md §2: Controller → validate → delegate to Service.
     */
    public function deleteAccount(Request $request)
    {
        try {
            $this->userService->deleteAccount($request->user());

            return response()->noContent(); // HTTP 204
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal menghapus akun. Silakan coba lagi.',
            ], 500);
        }
    }

    /**
     * POST /api/forgot-password
     * Delegates password reset link generation to UserService.
     * Always returns success to prevent email enumeration attacks.
     */
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $this->userService->sendPasswordResetLink($request->email);

        return response()->json([
            'status' => 'success',
            'message' => 'Jika email terdaftar, link reset kata sandi telah dikirim.',
        ]);
    }

    /**
     * POST /api/reset-password
     * Delegates password reset execution to UserService.
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        try {
            $this->userService->resetPassword(
                $request->email,
                $request->token,
                $request->password,
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Kata sandi berhasil direset. Silakan masuk dengan kata sandi baru.',
            ]);
        } catch (HttpException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }
}
