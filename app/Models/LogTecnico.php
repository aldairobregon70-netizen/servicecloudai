<?php

namespace App\Models;

use App\Enums\NivelLog;
use App\Models\Concerns\PerteneceAOrganizacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** recibido_en lo pone la BD (DEFAULT CURRENT_TIMESTAMP). */
class LogTecnico extends Model
{
    use PerteneceAOrganizacion;

    public $timestamps = false;

    protected $table = 'logs_tecnicos';

    protected $fillable = [
        'organizacion_id', 'servicio_id', 'activo_id', 'ticket_id', 'origen', 'nivel', 'mensaje', 'metadatos', 'fecha_evento',
    ];

    protected function casts(): array
    {
        return [
            'nivel' => NivelLog::class,
            'metadatos' => 'array',
            'fecha_evento' => 'datetime',
            'recibido_en' => 'datetime',
        ];
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Activo::class, 'activo_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }
}
