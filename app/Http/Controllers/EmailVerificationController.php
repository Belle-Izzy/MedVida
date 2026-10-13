<?php

namespace App\Http\Controllers;

use App\Models\Clinica;
use App\Models\Paciente;
use App\Services\RegistrationEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EmailVerificationController extends Controller
{
    public function verify(Request $request, string $tipo, string $id, string $hash): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'Link de confirmação inválido ou vencido.');
        $model = match ($tipo) {
            'paciente' => Paciente::class,
            'clinica' => Clinica::class,
            default => null,
        };
        abort_unless($model, 404);
        $account = $model::findOrFail($id);
        abort_unless(hash_equals(sha1($account->getEmailForVerification()), $hash), 403);
        if (! $account->hasVerifiedEmail()) $account->markEmailAsVerified();

        return redirect('/?email_verificado=1');
    }

    public function resend(Request $request, RegistrationEmail $mail): JsonResponse
    {
        $data = $request->validate([
            'tipo' => ['required', 'in:paciente,clinica'],
            'email' => ['required', 'email'],
            'senha' => ['required', 'string'],
        ]);
        $model = $data['tipo'] === 'clinica' ? Clinica::class : Paciente::class;
        $account = $model::where('email', $data['email'])->first();
        if ($account && Hash::check($data['senha'], $account->senha) && ! $account->hasVerifiedEmail()) {
            try {
                $account->sendEmailVerificationNotification();
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return response()->json(['message' => $mail->canDeliver()
            ? 'Se os dados estiverem corretos, enviamos um novo link de confirmação.'
            : 'O envio real de e-mails ainda não está configurado neste servidor. Em desenvolvimento, o link fica no log.']);
    }
}
