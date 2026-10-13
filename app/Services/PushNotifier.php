<?php

namespace App\Services;

use App\Models\Clinica;
use App\Models\Paciente;
use App\Models\PushSubscription;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotifier
{
    public function publicKey(): ?string
    {
        return $this->credentials()['publicKey'] ?? null;
    }

    public function enabled(): bool
    {
        return $this->credentials() !== null;
    }

    public function send(Paciente|Clinica $recipient, string $title, string $body, string $destination, string $tag): bool
    {
        try {
            $credentials = $this->credentials();
            if (! $credentials) return false;
            $subscriptions = PushSubscription::query()
                ->where('owner_type', $recipient instanceof Paciente ? 'paciente' : 'clinica')
                ->where('owner_id', $recipient->id)
                ->whereIn('access_token_id', DB::table('personal_access_tokens')->select('id'))
                ->get();
            if ($subscriptions->isEmpty()) return false;

            $push = new WebPush(['VAPID' => [
                'subject' => config('services.medvida_push.subject'),
                'publicKey' => $credentials['publicKey'],
                'privateKey' => $credentials['privateKey'],
            ]], ['TTL' => 86400, 'urgency' => 'normal'], new Client([
                'timeout' => 8,
                'connect_timeout' => 3,
                'allow_redirects' => false,
            ]));
            $payload = json_encode(compact('title', 'body', 'destination', 'tag'), JSON_THROW_ON_ERROR);
            $queued = [];
            foreach ($subscriptions as $subscription) {
                $push->queueNotification(Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'keys' => ['p256dh' => $subscription->p256dh, 'auth' => $subscription->auth],
                    'contentEncoding' => $subscription->content_encoding,
                ]), $payload);
                $queued[$subscription->endpoint_hash] = $subscription;
            }

            $sent = false;
            foreach ($push->flush() as $report) {
                $row = $queued[hash('sha256', $report->getEndpoint())] ?? null;
                if ($report->isSuccess()) {
                    $sent = true;
                } elseif ($report->isSubscriptionExpired()) {
                    $row?->delete();
                } else {
                    Log::warning('Falha ao enviar notificação Web Push.', ['subscription_id' => $row?->id, 'status' => $report->getResponse()?->getStatusCode()]);
                }
            }
            return $sent;
        } catch (\Throwable $error) {
            Log::warning('Notificação Web Push indisponível.', ['exception' => $error::class]);
            return false;
        }
    }

    private function credentials(): ?array
    {
        $public = config('services.medvida_push.public_key');
        $private = config('services.medvida_push.private_key');
        return $public && $private && config('services.medvida_push.subject')
            ? ['publicKey' => $public, 'privateKey' => $private] : null;
    }
}
