<?php

namespace App\Services;

use App\Models\Clinica;
use App\Models\PagamentoClinica;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class MercadoPagoCheckout
{
    public function configured(): bool
    {
        $url = config('services.mercado_pago.return_url');

        return filled(config('services.mercado_pago.access_token'))
            && filled(config('services.mercado_pago.webhook_secret'))
            && is_string($url)
            && filter_var($url, FILTER_VALIDATE_URL)
            && parse_url($url, PHP_URL_SCHEME) === 'https'
            && ! in_array(parse_url($url, PHP_URL_HOST), ['localhost', '127.0.0.1'], true);
    }

    public function start(Clinica $clinica): PagamentoClinica
    {
        if (! $this->configured()) {
            throw new RuntimeException('O pagamento ainda não está configurado neste servidor.');
        }

        $payment = DB::transaction(function () use ($clinica) {
            Clinica::whereKey($clinica->id)->lockForUpdate()->firstOrFail();
            $existing = $clinica->pagamentos()->where('tipo', 'plano_anual')
                ->where('status', 'aguardando_pagamento')->latest('id')->first();
            if ($existing && $existing->created_at->gt(now()->subDay())) return $existing;

            return $clinica->pagamentos()->create([
                'tipo' => 'plano_anual', 'forma_pagamento' => 'checkout_pro',
                'status' => 'aguardando_pagamento', 'simulado' => false,
                'referencia_externa' => (string) Str::uuid(),
                'chave_idempotencia' => (string) Str::uuid(),
                'valor_centavos' => config('medvida.plano_anual_centavos'), 'moeda' => 'BRL',
            ]);
        });
        if ($payment->checkout_url) return $payment;

        $returnUrl = rtrim(config('services.mercado_pago.return_url'), '/');
        $response = Http::withToken(config('services.mercado_pago.access_token'))
            ->acceptJson()->timeout(12)
            ->withHeaders(['X-Idempotency-Key' => $payment->chave_idempotencia])
            ->post('https://api.mercadopago.com/v1/orders', [
                'type' => 'online',
                'processing_mode' => 'manual',
                'total_amount' => number_format($payment->valor_centavos / 100, 2, '.', ''),
                'external_reference' => $payment->referencia_externa,
                'description' => 'MedVida - plano anual',
                'expiration_time' => 'P1D',
                'payer' => ['email' => $clinica->email],
                'config' => ['online' => [
                    'success_url' => $returnUrl.'/?pagamento=retorno',
                    'failure_url' => $returnUrl.'/?pagamento=retorno',
                    'pending_url' => $returnUrl.'/?pagamento=retorno',
                    'auto_return' => 'all',
                ]],
            ]);

        $orderId = $response->json('id');
        $checkoutUrl = $response->json('checkout_url');
        if (! $response->successful() || ! is_string($orderId) || ! preg_match('/^ORD[A-Z0-9]+$/', $orderId)
            || ! $this->safeCheckoutUrl($checkoutUrl)
            || $response->json('external_reference') !== $payment->referencia_externa) {
            throw new RuntimeException('Não foi possível abrir o pagamento. Tente novamente em instantes.');
        }

        $payment->update(['referencia_provedor' => $orderId, 'checkout_url' => $checkoutUrl]);

        return $payment->fresh();
    }

    public function validSignature(string $orderId, ?string $signature, ?string $requestId): bool
    {
        $secret = config('services.mercado_pago.webhook_secret');
        if (! is_string($secret) || $secret === '' || ! $requestId || ! $signature) return false;
        if (! preg_match('/(?:^|,)ts=(\d+)(?:,|$)/', $signature, $timestamp)) return false;
        if (! preg_match('/(?:^|,)v1=([a-fA-F0-9]{64})(?:,|$)/', $signature, $digest)) return false;

        $manifest = "id:{$orderId};request-id:{$requestId};ts:{$timestamp[1]};";

        return hash_equals(hash_hmac('sha256', $manifest, $secret), strtolower($digest[1]));
    }

    public function syncOrder(string $orderId): bool
    {
        if (! preg_match('/^ORD[A-Z0-9]+$/', $orderId)) return false;
        $payment = PagamentoClinica::where('referencia_provedor', $orderId)->first();
        if (! $payment) return false;

        $response = Http::withToken(config('services.mercado_pago.access_token'))
            ->acceptJson()->timeout(12)->get('https://api.mercadopago.com/v1/orders/'.$orderId);
        if (! $response->successful()) throw new RuntimeException('Não foi possível conferir o pagamento.');
        $order = $response->json();
        if (! is_array($order)
            || ($order['id'] ?? null) !== $orderId
            || ($order['external_reference'] ?? null) !== $payment->referencia_externa
            || ($order['currency'] ?? null) !== 'BRL'
            || $this->cents($order['total_amount'] ?? null) !== $payment->valor_centavos) {
            throw new RuntimeException('Os dados da cobrança não conferem.');
        }
        if (in_array($order['status_detail'] ?? null, ['refunded', 'partially_refunded'], true)
            || ($order['status'] ?? null) === 'refunded') {
            DB::transaction(function () use ($payment) {
                $locked = PagamentoClinica::whereKey($payment->id)->lockForUpdate()->firstOrFail();
                if ($locked->status === 'estornado') return;
                $wasApproved = $locked->status === 'aprovado';
                $locked->update(['status' => 'estornado']);
                if (! $wasApproved) return;
                $clinic = Clinica::whereKey($locked->clinica_id)->lockForUpdate()->firstOrFail();
                if ($clinic->ativacao_origem === 'mercado_pago'
                    && $clinic->acesso_ate?->equalTo($locked->acesso_fim)) {
                    $clinic->update(['status_cadastro' => 'aguardando_pagamento', 'acesso_ate' => now()]);
                }
            });

            return false;
        }
        if (($order['status'] ?? null) !== 'processed' || ($order['status_detail'] ?? null) !== 'accredited') {
            if (in_array($order['status'] ?? null, ['failed', 'canceled'], true)) {
                $payment->update(['status' => 'recusado']);
            }

            return false;
        }
        if ($this->cents($order['total_paid_amount'] ?? null) !== $payment->valor_centavos) {
            throw new RuntimeException('O valor recebido não confere.');
        }

        DB::transaction(function () use ($payment) {
            $locked = PagamentoClinica::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'aprovado' || $locked->status === 'estornado') return;
            $clinic = Clinica::whereKey($locked->clinica_id)->lockForUpdate()->firstOrFail();
            $start = $clinic->acesso_ate?->isFuture() ? $clinic->acesso_ate->copy() : now();
            $end = $start->copy()->addYear();
            $locked->update(['status' => 'aprovado', 'pago_em' => now(), 'acesso_inicio' => $start, 'acesso_fim' => $end]);
            $clinic->update(['status_cadastro' => 'ativa', 'ativada_em' => $clinic->ativada_em ?? now(), 'ativacao_origem' => 'mercado_pago', 'acesso_ate' => $end]);
        });

        return true;
    }

    private function cents(mixed $amount): ?int
    {
        if (! is_string($amount) || ! preg_match('/^\d+\.\d{2}$/', $amount)) return null;
        [$reais, $centavos] = explode('.', $amount);

        return (int) $reais * 100 + (int) $centavos;
    }

    private function safeCheckoutUrl(mixed $url): bool
    {
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) return false;
        $host = parse_url($url, PHP_URL_HOST);

        return parse_url($url, PHP_URL_SCHEME) === 'https'
            && is_string($host)
            && ($host === 'mercadopago.com.br' || str_ends_with($host, '.mercadopago.com.br'));
    }
}
