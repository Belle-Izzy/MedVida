<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Disponibilidade extends Model
{
    use HasFactory;

    protected $fillable = [
        'medico_id',
        'data',
        'horario',
        'disponivel',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'horario' => 'datetime:H:i',
            'disponivel' => 'boolean',
        ];
    }

    public function medico(): BelongsTo
    {
        return $this->belongsTo(Medico::class);
    }
}