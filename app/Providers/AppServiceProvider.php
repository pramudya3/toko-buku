<?php

namespace App\Providers;

use App\Enums\ActivityAction;
use App\Support\ActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerActivityListeners();
    }

    /**
     * Catat aktivitas auth (login, logout, login gagal).
     */
    protected function registerActivityListeners(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            ActivityLogger::log(
                ActivityAction::Login,
                'Login berhasil',
                user: $event->user,
            );
        });

        Event::listen(Logout::class, function (Logout $event): void {
            ActivityLogger::log(
                ActivityAction::Logout,
                'Logout',
                user: $event->user,
            );
        });

        Event::listen(Failed::class, function (Failed $event): void {
            $email = $event->user?->email ?? ($event->credentials['email'] ?? null);

            ActivityLogger::log(
                ActivityAction::LoginFailed,
                'Login gagal'.($email !== null ? ' untuk '.$email : ''),
            );
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
