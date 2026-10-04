<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medico extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinica_id',
        'nome',
        'crm',
        'especialidade',
        'telefone',
    ];

    public function clinica(): BelongsTo
    {
        return $this->belongsTo(Clinica::class);
    }

    public function consultas(): HasMany
    {
        return $this->hasMany(Consulta::class);
    }

    public function disponibilidades(): HasMany
    {
        return $this->hasMany(Disponibilidade::class);
    }
}