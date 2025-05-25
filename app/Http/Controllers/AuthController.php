<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Auth\Events\Registered;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'email' => 'required|email|unique:users',
            'password' => ['required', 'confirmed', PasswordRule::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised()],
        ]);

        try {
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => User::ROLE_USER,
            ]);

            event(new Registered($user)); // This will trigger email verification notification

            // For Angular integration, you might want to return a token as well
            $token = $user->createToken($request->device_name ?? 'web')->plainTextToken;

            return response()->json([
                'message' => 'User registered successfully. Please check your email for verification.',
                'user' => $user->only(['id', 'first_name', 'last_name', 'email', 'role']),
                'access_token' => $token,
                'token_type' => 'Bearer',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating user. Please try again later.'
            ], 500);
        }
    }

   public function login(Request $request): JsonResponse
{
    $request->validate([
        'email' => 'required|email',
        'password' => 'required|string',
        'device_name' => 'required|string',
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json([
            'message' => 'The provided credentials are incorrect.'
        ], 401);
    }

    // Cambia esta línea:
    $token = $user->createToken($request->device_name)->plainTextToken;

    return response()->json([
        'message' => 'Login successful',
        'access_token' => $token, // Ya es un string
        'token_type' => 'Bearer',
        'user' => $user->only(['id', 'first_name', 'last_name', 'email', 'role'])
    ]);
}

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }

    public function user(Request $request): JsonResponse
{
    return response()->json([
        'user' => $request->user()->only([
            'id', 'first_name', 'last_name', 'email', 'role'
        ])
    ]);
}

    /**
     * Send password reset link
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 400);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 400);
    }

    public function verifyToken(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Token is valid',
            'user' => $request->user()->only(['id', 'first_name', 'last_name', 'email', 'role'])
        ]);
    }

    /**
     * Verify email with verification token
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $user = User::find($request->route('id'));

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if (!hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid verification link'], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified']);
        }

        $user->markEmailAsVerified();

        return response()->json(['message' => 'Email verified successfully']);
    }

    /**
     * Resend verification email
     */
    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification link sent']);
    }

    /**
     * Verify user role
     */
    public function verifyRole(Request $request, string $role): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['allowed' => false], 401);
        }

        $allowed = false;

        switch ($role) {
            case 'admin':
                $allowed = $user->role === User::ROLE_ADMIN;
                break;
            case 'staff':
                $allowed = in_array($user->role, [User::ROLE_ADMIN, User::ROLE_STAFF]);
                break;
            case 'user':
                $allowed = in_array($user->role, [User::ROLE_ADMIN, User::ROLE_STAFF, User::ROLE_USER]);
                break;
            default:
                $allowed = false;
        }

        return response()->json(['allowed' => $allowed]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:50',
            'last_name' => 'sometimes|string|max:50',
            'phone_country_code' => 'sometimes|string|max:5|nullable',
            'phone_number' => 'sometimes|string|max:20|nullable',
            'street' => 'sometimes|string|max:100|nullable',
            'city' => 'sometimes|string|max:50|nullable',
            'state' => 'sometimes|string|max:50|nullable',
            'postal_code' => 'sometimes|string|max:20|nullable',
            'country_code' => 'sometimes|string|max:2|nullable',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->only([
                'id', 'first_name', 'last_name', 'email',
                'phone_country_code', 'phone_number',
                'street', 'city', 'state', 'postal_code', 'country_code',
                'role'
            ])
        ]);
    }

    /**
     * Change user password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', PasswordRule::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised()],
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 422);
        }

        $user->update([
            'password' => Hash::make($validated['password'])
        ]);

        // Revoke all tokens except the current one
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return response()->json([
            'message' => 'Password changed successfully'
        ]);
    }
}
