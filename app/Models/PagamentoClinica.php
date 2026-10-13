<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagamentoClinica extends Model
{
    use HasFactory;

    protected $table = 'pagamentos_clinica';

    protected $fillable = [
        'clinica_id', 'tipo', 'forma_pagamento', 'status', 'simulado',
        'referencia_provedor', 'pago_em', 'referencia_externa',
        'chave_idempotencia', 'valor_centavos', 'moeda', 'checkout_url',
        'operador_admin', 'motivo_admin', 'acesso_inicio', 'acesso_fim',
    ];

    protected function casts(): array
    {
        return [
            'simulado' => 'boolean',
            'pago_em' => 'datetime',
            'acesso_inicio' => 'datetime',
            'acesso_fim' => 'datetime',
        ];
    }

    public function clinica(): BelongsTo
    {
        return $this->belongsTo(Clinica::class);
    }
}
