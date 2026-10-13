<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Clinica extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use MustVerifyEmailTrait;
    use Notifiable;

    protected $fillable = [
        'nome', 'plano_atendido', 'telefone', 'endereco', 'email', 'senha',
        'status_cadastro', 'ativada_em', 'acesso_ate', 'ativacao_origem', 'email_verified_at',
    ];

    protected $hidden = ['senha'];

    protected function casts(): array
    {
        return ['ativada_em' => 'datetime', 'acesso_ate' => 'datetime', 'email_verified_at' => 'datetime'];
    }

    public function medicos(): HasMany
    {
        return $this->hasMany(Medico::class);
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(PagamentoClinica::class);
    }

    public function hasVerifiedPayment(): bool
    {
        return $this->pagamentos()
            ->where('tipo', 'plano_anual')
            ->where('status', 'aprovado')
            ->where('simulado', false)
            ->whereNotNull('pago_em')
            ->exists();
    }

    public function hasActiveAccess(): bool
    {
        return $this->status_cadastro === 'ativa'
            && $this->acesso_ate?->isFuture()
            && ($this->ativacao_origem === 'admin'
                || ($this->ativacao_origem === 'prototipo' && \App\Support\DemoMode::prototype())
                || $this->hasVerifiedPayment());
    }
}
