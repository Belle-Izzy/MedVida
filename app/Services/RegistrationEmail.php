<?php

namespace App\Services;

use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\Authenticatable;

class RegistrationEmail
{
    public function canDeliver(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array'], true)
            && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)
            && config('mail.from.address') !== 'hello@example.com';
    }

    public function send(Authenticatable $account): bool
    {
        try {
            event(new Registered($account));

            return $this->canDeliver();
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
