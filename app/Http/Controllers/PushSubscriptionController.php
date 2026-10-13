<?php

namespace App\Http\Controllers;

use App\Models\Clinica;
use App\Models\PushSubscription;
use App\Services\PushNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PushSubscriptionController extends Controller
{
    public function config(PushNotifier $notifier): JsonResponse
    {
        return response()->json(['public_key' => $notifier->publicKey()]);
    }

    public function store(Request $request, PushNotifier $notifier): JsonResponse
    {
        abort_unless($notifier->enabled(), 503, 'As notificações ainda não foram configuradas.');
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{80,120}$/'],
            'keys.auth' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,40}$/'],
            'contentEncoding' => ['sometimes', 'in:aes128gcm,aesgcm'],
        ]);
        $this->validateEndpoint($data['endpoint']);

        $user = $request->user();
        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            [
                'endpoint' => $data['endpoint'],
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
                'owner_type' => $user instanceof Clinica ? 'clinica' : 'paciente',
                'owner_id' => $user->id,
                'access_token_id' => $user->currentAccessToken()->id,
            ],
        );

        return response()->json(['message' => 'Notificações ativadas.'], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2048']]);
        PushSubscription::query()
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))
            ->where('access_token_id', $request->user()->currentAccessToken()->id)
            ->delete();

        return response()->json(['message' => 'Notificações desativadas.']);
    }

    private function validateEndpoint(string $endpoint): void
    {
        $parts = parse_url($endpoint);
        $host = strtolower($parts['host'] ?? '');
        $allowed = config('services.medvida_push.allowed_hosts', []);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || ! in_array($host, $allowed, true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['fragment'])
            || empty($parts['path'])) {
            throw ValidationException::withMessages(['endpoint' => 'Serviço de notificações não permitido.']);
        }
    }
}
