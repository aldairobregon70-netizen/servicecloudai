<?php

namespace App\Models;

use App\Enums\Prioridad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClasificacionIa extends Model
{
    const CREATED_AT = 'creado_en';

    const UPDATED_AT = null;

    protected $table = 'clasificaciones_ia';

    protected $fillable = [
        'ticket_id', 'modelo_id', 'categoria_sugerida', 'prioridad_sugerida', 'confianza', 'explicacion', 'aceptada',
    ];

    protected function casts(): array
    {
        return [
            'prioridad_sugerida' => Prioridad::class,
            'confianza' => 'float',
            'aceptada' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function modelo(): BelongsTo
    {
        return $this->belongsTo(ModeloIa::class, 'modelo_id');
    }
}
