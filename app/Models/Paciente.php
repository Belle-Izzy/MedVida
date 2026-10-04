<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class Paciente extends Model
{
    use HasFactory;
    use HasApiTokens;

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
    ];

    protected $hidden = ['senha'];

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
        ];
    }

    public function consultas(): HasMany
    {
        return $this->hasMany(Consulta::class);
    }
}