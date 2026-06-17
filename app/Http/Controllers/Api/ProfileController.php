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
    $user = $request->user();
    return response()->json([
        'success' => true,
        'user' => $user->only([
            'id', 'first_name', 'last_name', 'email',
            'phone_country_code', 'phone_number',
            'street', 'city', 'state', 'postal_code', 'country_code',
            'created_at',
        ]),
    ]);
}

    public function update(Request $request): JsonResponse
{
    $user = $request->user();

    $validated = $request->validate([
        'street'       => 'nullable|string|max:255',
        'city'         => 'nullable|string|max:100',
        'state'        => 'nullable|string|max:100',
        'postal_code'  => 'nullable|string|max:20',
        'country_code' => 'nullable|string|size:2|regex:/^[A-Za-z]{2}$/',
    ]);

    if (isset($validated['country_code'])) {
        $validated['country_code'] = strtoupper($validated['country_code']);
    }

    $user->update($validated);

    return response()->json([
    'success' => true,
    'message' => 'Dirección actualizada correctamente.',
    'user' => $user->fresh()->only([
        'id', 'first_name', 'last_name', 'email',
        'phone_country_code', 'phone_number',
        'street', 'city', 'state', 'postal_code', 'country_code',
        'created_at'
    ])
]);
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
