<?php

namespace App\Models;

use App\Enums\CanalNotificacion;
use App\Enums\TipoNotificacion;
use App\Models\Concerns\PerteneceAOrganizacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notificacion extends Model
{
    use PerteneceAOrganizacion;

    protected static bool $incluyeGlobales = true;

    const CREATED_AT = 'creado_en';

    const UPDATED_AT = null;

    protected $table = 'notificaciones';

    protected $fillable = [
        'organizacion_id', 'usuario_id', 'ticket_id', 'titulo', 'mensaje', 'tipo', 'canal', 'leida_en', 'enviada_en',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoNotificacion::class,
            'canal' => CanalNotificacion::class,
            'leida_en' => 'datetime',
            'enviada_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function scopeNoLeidas(Builder $consulta): Builder
    {
        return $consulta->whereNull('leida_en');
    }

    public function scopeDe(Builder $consulta, Usuario $usuario): Builder
    {
        return $consulta->where('usuario_id', $usuario->id);
    }
}
