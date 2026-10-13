<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Paciente extends Authenticatable implements MustVerifyEmail
{
    use HasFactory;
    use HasApiTokens;
    use MustVerifyEmailTrait;
    use Notifiable;

    protected $fillable = [
        'cpf',
        'telefone',
        'plano',
        'nome',
        'data_nascimento',
        'sexo',
        'caracteristicas_saude',
        'email',
        'senha',
        'email_verified_at',
    ];

    protected $hidden = ['senha'];

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
            'email_verified_at' => 'datetime',
        ];
    }

    public function consultas(): HasMany
    {
        return $this->hasMany(Consulta::class);
    }
}
