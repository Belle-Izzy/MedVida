<?php

namespace App\Http\Controllers;

use App\Services\MercadoPagoCheckout;
use App\Support\DemoMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PagamentoClinicaController extends Controller
{
    public function activatePrototype(Request $request): JsonResponse
    {
        abort_unless(DemoMode::prototype(), 403, 'Ativação gratuita indisponível neste ambiente.');

        $clinic = DB::transaction(function () use ($request) {
            $clinic = $request->user()->newQuery()->lockForUpdate()->findOrFail($request->user()->getKey());
            abort_unless($clinic->status_cadastro === 'aguardando_pagamento', 409, 'A conta já foi ativada.');

            $start = now();
            $clinic->update([
                'status_cadastro' => 'ativa',
                'ativada_em' => $start,
                'acesso_ate' => $start->copy()->addYear(),
                'ativacao_origem' => 'prototipo',
            ]);
            $clinic->tokens()->delete();

            return $clinic->fresh();
        });

        return response()->json(['message' => 'Clínica ativada. Entre com seu e-mail e senha.', 'clinica' => $clinic]);
    }

    public function checkout(Request $request, MercadoPagoCheckout $checkout): JsonResponse
    {
        if (DemoMode::enabled()) {
            return response()->json(['message' => 'Pagamentos estão desativados na demonstração.'], 403);
        }
        if ($request->user()->hasActiveAccess()) {
            return response()->json(['message' => 'O plano anual da clínica já está ativo.'], 409);
        }
        if (! $checkout->configured()) {
            return response()->json(['message' => 'O pagamento ainda não está configurado neste servidor.'], 503);
        }

        try {
            $payment = $checkout->start($request->user());
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Não foi possível abrir o pagamento. Tente novamente.'], 502);
        }

        return response()->json(['checkout_url' => $payment->checkout_url]);
    }

    public function status(Request $request, MercadoPagoCheckout $checkout): JsonResponse
    {
        $payment = $request->user()->pagamentos()->where('tipo', 'plano_anual')->latest('id')->first();
        if ($payment?->referencia_provedor && $checkout->configured()) {
            try {
                $checkout->syncOrder($payment->referencia_provedor);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        $clinic = $request->user()->fresh();

        return response()->json([
            'ativo' => $clinic->hasActiveAccess(),
            'status_cadastro' => $clinic->status_cadastro,
            'acesso_ate' => $clinic->acesso_ate,
            'pagamento_status' => $payment?->fresh()->status,
        ]);
    }

    public function webhook(Request $request, MercadoPagoCheckout $checkout): JsonResponse
    {
        // PHP normaliza pontos em nomes de parâmetros da query para sublinhados.
        $orderId = $request->query('data_id', $request->query('data.id'));
        if ($request->query('type') !== 'order' || ! is_string($orderId)
            || ! $checkout->validSignature($orderId, $request->header('x-signature'), $request->header('x-request-id'))) {
            return response()->json(['message' => 'Notificação inválida.'], 401);
        }

        try {
            $checkout->syncOrder($orderId);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Não foi possível conferir a order.'], 503);
        }

        return response()->json(['received' => true]);
    }
}
