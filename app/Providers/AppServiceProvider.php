<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        // ─── LOGIN ─────────────────────────────────
        RateLimiter::for('login', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));
            return [
                Limit::perMinute(5)->by('login-ip:' . $request->ip()),
                Limit::perMinute(5)->by('login-email:' . $email),
            ];
        });

        // ─── REGISTER ──────────────────────────────
        RateLimiter::for('register', function (Request $request) {
            return Limit::perHour(3)->by('register:' . $request->ip());
        });

        // ─── PASSWORD RESET ────────────────────────
        RateLimiter::for('password-reset', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));
            return [
                Limit::perHour(3)->by('pwreset-ip:' . $request->ip()),
                Limit::perHour(3)->by('pwreset-email:' . $email),
            ];
        });

        // ─── CAMBIO DE CONTRASEÑA (autenticado) ────
        RateLimiter::for('password-change', function (Request $request) {
            return Limit::perHour(5)
                ->by(optional($request->user())->id ?: $request->ip());
        });

        // ─── VALIDACIÓN DE CÓDIGO DE DESCUENTO ─────
        RateLimiter::for('discount-validate', function (Request $request) {
            return Limit::perMinute(10)->by(
                'discount:' . (optional($request->user())->id ?: $request->ip())
            );
        });

        // ─── API PÚBLICA ───────────────────────────
        RateLimiter::for('public-api', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        // ─── API AUTENTICADA ───────────────────────
        RateLimiter::for('user-api', function (Request $request) {
            return Limit::perMinute(60)
                ->by(optional($request->user())->id ?: $request->ip());
        });

        // ─── API ADMIN ─────────────────────────────
        RateLimiter::for('admin-api', function (Request $request) {
            return Limit::perMinute(300)
                ->by(optional($request->user())->id ?: $request->ip());
        });
    }
}
