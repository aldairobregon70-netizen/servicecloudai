<?php

namespace App\Models;

use App\Enums\RolMensajeIa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MensajeIa extends Model
{
    const CREATED_AT = 'creado_en';

    const UPDATED_AT = null;

    protected $table = 'mensajes_ia';

    protected $fillable = ['conversacion_id', 'rol', 'contenido', 'modelo_id', 'tokens_entrada', 'tokens_salida'];

    protected function casts(): array
    {
        return [
            'rol' => RolMensajeIa::class,
            'tokens_entrada' => 'integer',
            'tokens_salida' => 'integer',
        ];
    }

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(ConversacionIa::class, 'conversacion_id');
    }

    public function modelo(): BelongsTo
    {
        return $this->belongsTo(ModeloIa::class, 'modelo_id');
    }
}
