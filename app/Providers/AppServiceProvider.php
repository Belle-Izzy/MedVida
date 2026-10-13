<?php

namespace App\Providers;

use App\Models\Clinica;
use App\Models\Paciente;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        if (parse_url(config('app.url'), PHP_URL_SCHEME) === 'https') {
            URL::forceRootUrl(rtrim(config('app.url'), '/'));
            URL::forceScheme('https');
        }

        VerifyEmail::createUrlUsing(function (Clinica|Paciente $account): string {
            return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
                'tipo' => $account instanceof Clinica ? 'clinica' : 'paciente',
                'id' => $account->getKey(),
                'hash' => sha1($account->getEmailForVerification()),
            ]);
        });
        VerifyEmail::toMailUsing(function ($account, string $url): MailMessage {
            return (new MailMessage)
                ->subject('Confirme seu e-mail no MedVida')
                ->greeting('Olá!')
                ->line('Confirme seu endereço de e-mail para concluir o cadastro no MedVida.')
                ->action('Confirmar e-mail', $url)
                ->line('O link é válido por 60 minutos. Se você não criou esta conta, ignore esta mensagem.');
        });

        RateLimiter::for('medvida-login', function (Request $request): array {
            $email = $request->input('email');
            $identifier = is_string($email) ? mb_strtolower(trim($email)) : '';
            $tipo = $request->input('tipo');
            $accountKey = hash('sha256', (is_string($tipo) ? $tipo : '').'|'.$identifier);

            return [
                Limit::perMinute(10)->by('login-ip|'.$request->ip()),
                Limit::perMinute(5)->by('login-account|'.$accountKey.'|'.$request->ip()),
            ];
        });
    }
}
