<?php

namespace App\Providers;

use App\Enums\ActivityAction;
use App\Support\ActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

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
        $this->configureEloquent();
        $this->registerActivityListeners();
        $this->configureMail();
    }

    protected function configureMail(): void
    {
        // Brevo API transport via symfony/brevo-mailer (MAIL_MAILER=brevo, BREVO_API_KEY)
        Mail::extend('brevo', function (array $config): TransportInterface {
            $factory = new BrevoTransportFactory(
                dispatcher: null,
                client: null,
                logger: app(LoggerInterface::class),
            );

            $dsn = new Dsn(
                'brevo+api',
                'default',
                $config['key'] ?? '',
            );

            return $factory->create($dsn);
        });
    }

    protected function configureEloquent(): void
    {
        Model::preventLazyLoading(! app()->isProduction() && ! app()->runningUnitTests());
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
            // Kebijakan sederhana utk customer toko buku: minimal 6 karakter
            // tanpa syarat huruf besar/kecil, angka, simbol, atau cek HIBP.
            ? Password::min(6)
            : null,
        );
    }
}
