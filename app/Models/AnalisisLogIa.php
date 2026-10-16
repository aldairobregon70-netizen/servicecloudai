<?php

namespace App\Models;

use App\Enums\SeveridadLog;
use App\Models\Concerns\PerteneceAOrganizacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AnalisisLogIa extends Model
{
    use PerteneceAOrganizacion;

    const CREATED_AT = 'creado_en';

    const UPDATED_AT = null;

    protected $table = 'analisis_logs_ia';

    protected $fillable = [
        'organizacion_id', 'ticket_id', 'modelo_id', 'resumen', 'posibles_causas', 'acciones_recomendadas', 'severidad',
    ];

    protected function casts(): array
    {
        return [
            'posibles_causas' => 'array',
            'acciones_recomendadas' => 'array',
            'severidad' => SeveridadLog::class,
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

    public function logs(): BelongsToMany
    {
        return $this->belongsToMany(LogTecnico::class, 'analisis_logs_detalle', 'analisis_id', 'log_id');
    }
}
