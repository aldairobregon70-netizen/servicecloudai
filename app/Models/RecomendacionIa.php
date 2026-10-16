<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RecomendacionIa extends Model
{
    const CREATED_AT = 'creado_en';

    const UPDATED_AT = null;

    protected $table = 'recomendaciones_ia';

    protected $fillable = ['ticket_id', 'modelo_id', 'diagnostico', 'causas_probables', 'pasos_sugeridos', 'confianza'];

    protected function casts(): array
    {
        return [
            'causas_probables' => 'array',
            'pasos_sugeridos' => 'array',
            'confianza' => 'float',
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

    /** Artículos usados como fuente, con su relevancia (0..1). */
    public function fuentes(): BelongsToMany
    {
        return $this->belongsToMany(ArticuloConocimiento::class, 'recomendaciones_fuentes', 'recomendacion_id', 'articulo_id')
            ->using(RecomendacionFuente::class)
            ->withPivot('relevancia')
            ->orderByPivot('relevancia', 'desc');
    }
}
