<?php

namespace App\Services;

use App\Enums\EstadoTicket;
use App\Enums\Impacto;
use App\Enums\Prioridad;
use App\Enums\TipoNotificacion;
use App\Enums\Urgencia;
use App\Models\Activo;
use App\Models\CategoriaTicket;
use App\Models\Servicio;
use App\Models\Ticket;
use App\Models\TicketAdjunto;
use App\Models\TicketAsignacion;
use App\Models\TicketComentario;
use App\Models\TicketHistorial;
use App\Models\Usuario;
use App\Services\AI\ClasificadorTickets;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Toda modificación de un ticket pasa por aquí: registra historial, asignaciones,
 * SLA y notificaciones (regla 02-laravel-postgres).
 */
class TicketService
{
    public function __construct(
        private SlaService $sla,
        private NotificacionService $notificaciones,
    ) {}

    // ------------------------------------------------------------------ crear

    public function crear(array $datos, Usuario $autor): Ticket
    {
        $ticket = DB::transaction(function () use ($datos, $autor) {
            $impacto = Impacto::from($datos['impacto'] ?? 'medio');
            $urgencia = Urgencia::from($datos['urgencia'] ?? 'media');
            $tecnicoId = $datos['tecnico_id'] ?? null;

            $ticket = new Ticket([
                'organizacion_id' => $datos['organizacion_id'],
                'codigo' => $this->siguienteCodigo(),
                'titulo' => $datos['titulo'],
                'descripcion' => $datos['descripcion'],
                'tipo' => $datos['tipo'] ?? 'incidente',
                'categoria_id' => $datos['categoria_id'] ?? null,
                'categoria' => $this->nombreCategoria($datos['categoria_id'] ?? null),
                'impacto' => $impacto,
                'urgencia' => $urgencia,
                'prioridad' => isset($datos['prioridad']) ? Prioridad::from($datos['prioridad']) : Prioridad::desdeMatriz($impacto, $urgencia),
                'estado' => $tecnicoId ? EstadoTicket::Asignado : EstadoTicket::Abierto,
                'origen' => $datos['origen'] ?? 'portal',
                'solicitante_id' => $datos['solicitante_id'] ?? $autor->id,
                'tecnico_id' => $tecnicoId,
                'equipo_id' => $datos['equipo_id'] ?? null,
                'servicio_id' => $datos['servicio_id'] ?? null,
                'activo_id' => $datos['activo_id'] ?? null,
            ]);
            $ticket->creado_en = now();
            $this->sla->aplicar($ticket);
            $ticket->save();
            $this->sla->sincronizarRegistro($ticket);

            $this->historial($ticket, $autor, 'creacion', null, 'Ticket creado desde '.$ticket->origen->label());
            if ($tecnicoId) {
                $this->registrarAsignacion($ticket, null, $tecnicoId, $autor, 'Asignación al registrar el ticket');
            }

            return $ticket;
        });

        if ($ticket->tecnico_id) {
            $this->notificaciones->notificar($ticket->tecnico_id, 'Ticket asignado', "Se te asignó {$ticket->codigo}: {$ticket->titulo}", TipoNotificacion::Asignacion, $ticket);
        }

        // Clasificación IA automática (si la organización la tiene activa). Nunca impide crear el ticket.
        app(ClasificadorTickets::class)->alCrearTicket($ticket, $autor);

        return $ticket->refresh();
    }

    /** TKT-AAAA-00001, correlativo por año (único en toda la plataforma). */
    public function siguienteCodigo(): string
    {
        $prefijo = 'TKT-'.now()->format('Y').'-';
        $maximo = Ticket::sinFiltroOrganizacion()->where('codigo', 'like', $prefijo.'%')
            ->selectRaw('max(substring(codigo from ?::int)::int) as maximo', [strlen($prefijo) + 1])
            ->value('maximo');

        return $prefijo.str_pad((string) ((int) $maximo + 1), 5, '0', STR_PAD_LEFT);
    }

    // ------------------------------------------------------------------ editar

    /** Actualiza datos descriptivos; prioridad y técnico pasan por sus métodos. */
    public function actualizar(Ticket $ticket, array $datos, Usuario $usuario): Ticket
    {
        return DB::transaction(function () use ($ticket, $datos, $usuario) {
            $prioridad = isset($datos['prioridad']) ? Prioridad::from($datos['prioridad']) : null;
            $tecnicoCambia = array_key_exists('tecnico_id', $datos);
            $tecnicoId = $datos['tecnico_id'] ?? null;
            unset($datos['prioridad'], $datos['tecnico_id']);

            if (array_key_exists('categoria_id', $datos)) {
                $datos['categoria'] = $this->nombreCategoria($datos['categoria_id']);
            }
            $ticket->fill($datos);
            foreach (['titulo', 'tipo', 'impacto', 'urgencia', 'servicio_id', 'activo_id', 'categoria_id'] as $campo) {
                if ($ticket->isDirty($campo)) {
                    $this->historial($ticket, $usuario, Str::before($campo, '_id'),
                        $this->valorTexto($campo, $ticket->getOriginal($campo)), $this->valorTexto($campo, $ticket->getAttribute($campo)));
                }
            }
            $ticket->save();

            if ($prioridad && $prioridad !== $ticket->prioridad) {
                $this->cambiarPrioridad($ticket, $prioridad, $usuario);
            }
            if ($tecnicoCambia && (int) $tecnicoId !== (int) $ticket->tecnico_id) {
                $this->asignar($ticket, $tecnicoId ? (int) $tecnicoId : null, $usuario);
            }

            return $ticket;
        });
    }

    public function cambiarPrioridad(Ticket $ticket, Prioridad $prioridad, Usuario $usuario, string $motivo = ''): void
    {
        $anterior = $ticket->prioridad;
        $ticket->prioridad = $prioridad;
        $regla = $this->sla->reglaPara($ticket->politicaSla()->withoutGlobalScopes()->first(), $prioridad);
        if ($regla) {
            $this->sla->recalcularVencimientos($ticket, $regla, $ticket->sla?->minutos_pausados ?? 0);
        }
        $ticket->save();
        if ($regla) {
            $ticket->sla()->update(['regla_sla_id' => $regla->id, 'calculado_en' => now()]);
            $ticket->unsetRelation('sla');
        }
        $this->historial($ticket, $usuario, 'prioridad', $anterior->value, $prioridad->value.($motivo ? " ($motivo)" : ''));
    }

    // ------------------------------------------------------------------ asignación

    public function asignar(Ticket $ticket, ?int $tecnicoId, Usuario $usuario, string $motivo = 'Asignación manual'): void
    {
        $anterior = $ticket->tecnico_id;
        if ((int) $anterior === (int) $tecnicoId) {
            return;
        }

        DB::transaction(function () use ($ticket, $tecnicoId, $usuario, $motivo, $anterior) {
            $ticket->tecnico_id = $tecnicoId;
            $estadoAnterior = $ticket->estado;
            if ($tecnicoId && $ticket->estado === EstadoTicket::Abierto) {
                $ticket->estado = EstadoTicket::Asignado;
            } elseif (! $tecnicoId && $ticket->estado === EstadoTicket::Asignado) {
                $ticket->estado = EstadoTicket::Abierto;
            }
            $ticket->save();
            $this->registrarAsignacion($ticket, $anterior, $tecnicoId, $usuario, $motivo);
            if ($estadoAnterior !== $ticket->estado) {
                $this->historial($ticket, $usuario, 'estado', $estadoAnterior->value, $ticket->estado->value);
            }
        });

        if ($tecnicoId && $tecnicoId !== $usuario->id) {
            $this->notificaciones->notificar($tecnicoId, 'Ticket asignado', "Se te asignó {$ticket->codigo}: {$ticket->titulo}", TipoNotificacion::Asignacion, $ticket);
        }
    }

    private function registrarAsignacion(Ticket $ticket, ?int $anterior, ?int $nuevo, Usuario $usuario, string $motivo): void
    {
        TicketAsignacion::create([
            'ticket_id' => $ticket->id, 'tecnico_anterior_id' => $anterior, 'tecnico_nuevo_id' => $nuevo,
            'equipo_id' => $ticket->equipo_id, 'asignado_por_id' => $usuario->id, 'motivo' => $motivo, 'fecha' => now(),
        ]);
        $nombre = $nuevo ? Usuario::sinFiltroOrganizacion()->whereKey($nuevo)->value('nombre') : 'Sin asignar';
        $this->historial($ticket, $usuario, 'tecnico', $anterior ? Usuario::sinFiltroOrganizacion()->whereKey($anterior)->value('nombre') : null, $nombre);
    }

    // ------------------------------------------------------------------ estado

    public function cambiarEstado(Ticket $ticket, EstadoTicket $nuevo, Usuario $usuario, ?string $solucion = null): void
    {
        $anterior = $ticket->estado;
        if ($anterior === $nuevo) {
            return;
        }
        if (! $anterior->puedePasarA($nuevo)) {
            throw ValidationException::withMessages(['estado' => "No se puede pasar de \"{$anterior->label()}\" a \"{$nuevo->label()}\"."]);
        }
        if ($nuevo === EstadoTicket::Resuelto && blank($solucion ?? $ticket->solucion)) {
            throw ValidationException::withMessages(['solucion' => 'Describe la solución para marcar el ticket como resuelto.']);
        }
        if (in_array($nuevo, [EstadoTicket::Asignado, EstadoTicket::EnProgreso], true) && ! $ticket->tecnico_id) {
            throw ValidationException::withMessages(['estado' => 'Asigna un técnico antes de pasar a "'.$nuevo->label().'".']);
        }

        DB::transaction(function () use ($ticket, $anterior, $nuevo, $usuario, $solucion) {
            $ahora = now();
            $ticket->estado = $nuevo;
            if (! $ticket->primera_respuesta_en && in_array($nuevo, [EstadoTicket::EnProgreso, EstadoTicket::EnEspera, EstadoTicket::Resuelto], true)) {
                $ticket->primera_respuesta_en = $ahora;
            }
            if ($nuevo === EstadoTicket::Resuelto) {
                $ticket->resuelto_en = $ahora;
                if (filled($solucion)) {
                    $ticket->solucion = $solucion;
                }
            }
            if ($anterior === EstadoTicket::Resuelto && $nuevo === EstadoTicket::EnProgreso) {
                $ticket->resuelto_en = null;
            }
            if ($nuevo === EstadoTicket::Cerrado) {
                $ticket->cerrado_en = $ahora;
            }
            $this->sla->alCambiarEstado($ticket, $anterior, $nuevo);
            $ticket->save();
            $this->historial($ticket, $usuario, 'estado', $anterior->value, $nuevo->value);
        });

        if ($ticket->solicitante_id !== $usuario->id) {
            $this->notificaciones->notificar($ticket->solicitante_id, 'Tu ticket cambió de estado', "{$ticket->codigo} pasó a {$nuevo->label()}.", TipoNotificacion::Ticket, $ticket);
        }
    }

    // ------------------------------------------------------------------ comentarios y adjuntos

    public function comentar(Ticket $ticket, Usuario $autor, string $contenido, bool $interno): TicketComentario
    {
        $comentario = TicketComentario::create(['ticket_id' => $ticket->id, 'autor_id' => $autor->id, 'contenido' => $contenido, 'es_interno' => $interno]);

        // La primera respuesta del técnico cuenta para el SLA de respuesta
        if (! $ticket->primera_respuesta_en && ! $interno && $autor->id === $ticket->tecnico_id) {
            $ticket->primera_respuesta_en = now();
            $ticket->save();
            $ticket->sla()->whereNull('respuesta_cumplida')->update(['respuesta_cumplida' => $ticket->primera_respuesta_en <= $ticket->vencimiento_respuesta]);
        }

        if (! $interno) {
            $destinatario = $autor->id === $ticket->solicitante_id ? $ticket->tecnico_id : $ticket->solicitante_id;
            $this->notificaciones->notificar($destinatario, 'Nuevo comentario', "{$autor->nombre} comentó en {$ticket->codigo}.", TipoNotificacion::Ticket, $ticket);
        }

        return $comentario;
    }

    public function adjuntar(Ticket $ticket, UploadedFile $archivo, Usuario $usuario): TicketAdjunto
    {
        $nombre = Str::uuid().'.'.($archivo->guessExtension() ?: $archivo->getClientOriginalExtension());
        $ruta = $archivo->storeAs("tickets/{$ticket->id}", $nombre, 'local');

        return TicketAdjunto::create([
            'ticket_id' => $ticket->id, 'subido_por_id' => $usuario->id,
            'nombre_original' => Str::limit($archivo->getClientOriginalName(), 250, ''),
            'ruta_storage' => $ruta, 'mime_type' => $archivo->getMimeType(), 'tamano_bytes' => $archivo->getSize(),
        ]);
    }

    // ------------------------------------------------------------------ apoyo

    public function historial(Ticket $ticket, ?Usuario $usuario, string $campo, ?string $anterior, ?string $nuevo): void
    {
        TicketHistorial::create([
            'ticket_id' => $ticket->id, 'usuario_id' => $usuario?->id, 'campo' => $campo,
            'valor_anterior' => $anterior, 'valor_nuevo' => $nuevo, 'fecha' => now(),
        ]);
    }

    private function nombreCategoria(?int $categoriaId): ?string
    {
        return $categoriaId ? CategoriaTicket::sinFiltroOrganizacion()->whereKey($categoriaId)->value('nombre') : null;
    }

    /** Valor legible para el historial (nombres en lugar de ids). */
    private function valorTexto(string $campo, mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        if ($valor instanceof \BackedEnum) {
            return method_exists($valor, 'label') ? $valor->label() : (string) $valor->value;
        }

        return match ($campo) {
            'servicio_id' => Servicio::sinFiltroOrganizacion()->whereKey($valor)->value('nombre'),
            'activo_id' => Activo::sinFiltroOrganizacion()->whereKey($valor)->value('nombre'),
            'categoria_id' => $this->nombreCategoria((int) $valor),
            default => (string) $valor,
        };
    }
}
