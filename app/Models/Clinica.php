<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class Clinica extends Model
{
    use HasApiTokens;
    use HasFactory;

    protected $fillable = [
        'nome', 'plano_atendido', 'telefone', 'endereco', 'email', 'senha',
    ];

    protected $hidden = ['senha'];

    public function medicos(): HasMany
    {
        return $this->hasMany(Medico::class);
    }
}
