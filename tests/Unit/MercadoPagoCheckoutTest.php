<?php

namespace Tests\Unit;

use App\Services\MercadoPagoCheckout;
use Tests\TestCase;

class MercadoPagoCheckoutTest extends TestCase
{
    public function test_checkout_requires_all_private_settings_and_https_return_url(): void
    {
        $checkout = new MercadoPagoCheckout();
        config()->set('services.mercado_pago.access_token', 'test-token');
        config()->set('services.mercado_pago.webhook_secret', 'test-secret');
        config()->set('services.mercado_pago.return_url', 'http://127.0.0.1:8001');
        $this->assertFalse($checkout->configured());

        config()->set('services.mercado_pago.return_url', 'https://medvida.example');
        $this->assertTrue($checkout->configured());
    }

    public function test_webhook_signature_uses_order_id_request_id_and_timestamp(): void
    {
        config()->set('services.mercado_pago.webhook_secret', 'test-secret');
        $checkout = new MercadoPagoCheckout();
        $order = 'ORD01ABC';
        $requestId = 'request-123';
        $timestamp = '1760000000000';
        $digest = hash_hmac('sha256', "id:{$order};request-id:{$requestId};ts:{$timestamp};", 'test-secret');

        $this->assertTrue($checkout->validSignature($order, "ts={$timestamp},v1={$digest}", $requestId));
        $this->assertFalse($checkout->validSignature('ORD01OTHER', "ts={$timestamp},v1={$digest}", $requestId));
        $this->assertFalse($checkout->validSignature($order, "ts={$timestamp},v1={$digest}", 'other'));
        $this->assertFalse($checkout->validSignature($order, null, $requestId));
    }
}
