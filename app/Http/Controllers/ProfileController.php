<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{

    public function show(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone_country_code' => $user->phone_country_code,
                    'phone_number' => $user->phone_number,
                    'street' => $user->street,
                    'city' => $user->city,
                    'state' => $user->state,
                    'postal_code' => $user->postal_code,
                    'country_code' => $user->country_code,
                    'role' => $user->role,
                    'phone_verified' => $user->phone_verified,
                    'formatted_phone' => $user->formatted_phone,
                    'remaining_changes' => $user->getRemainingProfileChanges(),
                    'can_change_profile' => $user->canChangeProfile(),
                    'last_profile_change' => $user->last_profile_change?->format('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching user profile', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el perfil'
            ], 500);
        }
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user->canChangeProfile()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Has excedido el límite de cambios de perfil este mes. Intenta de nuevo más tarde.',
                    'remaining_changes' => 0
                ], 429);
            }

            $key = 'profile-update:' . $request->ip();
            if (RateLimiter::tooManyAttempts($key, 3)) {
                $seconds = RateLimiter::availableIn($key);
                return response()->json([
                    'success' => false,
                    'message' => "Demasiados intentos. Intenta de nuevo en {$seconds} segundos."
                ], 429);
            }

            RateLimiter::hit($key, 300); // 5 minutos

            $validated = $request->validated();

            $significantFields = [
                'phone_country_code', 'phone_number',
                'street', 'city', 'state', 'postal_code', 'country_code'
            ];

            $hasSignificantChanges = false;
            foreach ($significantFields as $field) {
                if (isset($validated[$field]) && $user->{$field} !== $validated[$field]) {
                    $hasSignificantChanges = true;
                    break;
                }
            }

            if (isset($validated['phone_number']) && $user->phone_number !== $validated['phone_number']) {
                $validated['phone_verified'] = false;
                $validated['phone_verified_at'] = null;
            }

            $user->update($validated);
            if ($hasSignificantChanges) {
                $user->incrementProfileChanges();
            }

            Log::info('Profile updated', [
                'user_id' => $user->id,
                'changes' => array_keys($validated),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'significant_change' => $hasSignificantChanges
            ]);

            $user->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Perfil actualizado correctamente',
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone_country_code' => $user->phone_country_code,
                    'phone_number' => $user->phone_number,
                    'street' => $user->street,
                    'city' => $user->city,
                    'state' => $user->state,
                    'postal_code' => $user->postal_code,
                    'country_code' => $user->country_code,
                    'role' => $user->role,
                    'phone_verified' => $user->phone_verified,
                    'formatted_phone' => $user->formatted_phone,
                    'remaining_changes' => $user->getRemainingProfileChanges(),
                    'can_change_profile' => $user->canChangeProfile(),
                    'last_profile_change' => $user->last_profile_change?->format('Y-m-d H:i:s'),
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating user profile', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    public function limits(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            return response()->json([
                'success' => true,
                'limits' => [
                    'max_changes_per_month' => User::MAX_PROFILE_CHANGES_PER_MONTH,
                    'remaining_changes' => $user->getRemainingProfileChanges(),
                    'can_change' => $user->canChangeProfile(),
                    'reset_date' => $user->profile_change_reset_date?->format('Y-m-d H:i:s'),
                    'last_change' => $user->last_profile_change?->format('Y-m-d H:i:s'),
                    'changes_used' => $user->profile_changes_count ?? 0,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching profile limits', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los límites'
            ], 500);
        }
    }

    public function changePassword(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'current_password' => 'required|string',
                'password' => 'required|string|min:12|confirmed',
            ]);

            $user = $request->user();

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La contraseña actual es incorrecta'
                ], 422);
            }

            $key = 'password-change:' . $user->id;
            if (RateLimiter::tooManyAttempts($key, 3)) {
                $seconds = RateLimiter::availableIn($key);
                return response()->json([
                    'success' => false,
                    'message' => "Demasiados intentos. Intenta de nuevo en {$seconds} segundos."
                ], 429);
            }

            RateLimiter::hit($key, 3600); // 1 hora

            $user->update([
                'password' => Hash::make($request->password)
            ]);

            Log::info('Password changed', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contraseña cambiada correctamente'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error changing password', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }
}
