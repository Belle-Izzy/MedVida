<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consulta extends Model
{
    use HasFactory;

    protected $fillable = [
        'medico_id', 'paciente_id', 'lugar', 'data', 'horario', 'status',
        'mensagem_paciente',
    ];

    protected function casts(): array
    {
        return ['data' => 'date', 'horario' => 'datetime:H:i'];
    }

    public function medico(): BelongsTo
    {
        return $this->belongsTo(Medico::class);
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }
}
