<?php

namespace App\Services;

use App\Enums\EstadoTicket;
use App\Enums\Rol;
use App\Models\Ticket;
use App\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Tablero kanban y autoasignación por carga. */
class AsignacionService
{
    /** Columnas del kanban (mismo orden que el mockup). */
    public const COLUMNAS = ['sin_asignar' => 'Sin asignar', 'asignado' => 'Asignado', 'en_progreso' => 'En progreso', 'en_espera' => 'En espera', 'resuelto' => 'Resuelto'];

    public function __construct(private TicketService $tickets) {}

    public static function columnaDe(Ticket $ticket): ?string
    {
        return match ($ticket->estado) {
            EstadoTicket::Abierto => $ticket->tecnico_id ? 'asignado' : 'sin_asignar',
            EstadoTicket::Asignado => 'asignado',
            EstadoTicket::EnProgreso => 'en_progreso',
            EstadoTicket::EnEspera => 'en_espera',
            EstadoTicket::Resuelto => 'resuelto',
            default => null,
        };
    }

    /**
     * Mueve una tarjeta a otra columna pasando por TicketService (historial, SLA, notificaciones).
     * Si un técnico mueve un ticket sin asignar, se lo asigna a sí mismo.
     */
    public function mover(Ticket $ticket, string $columna, Usuario $usuario, ?string $solucion = null): void
    {
        if (! array_key_exists($columna, self::COLUMNAS)) {
            throw ValidationException::withMessages(['columna' => 'Columna no válida.']);
        }
        if (self::columnaDe($ticket) === $columna) {
            return;
        }

        if ($columna === 'sin_asignar') {
            if (! in_array($ticket->estado, [EstadoTicket::Abierto, EstadoTicket::Asignado], true)) {
                throw ValidationException::withMessages(['columna' => 'Solo un ticket abierto o asignado puede volver a "Sin asignar".']);
            }
            $this->tickets->asignar($ticket, null, $usuario, 'Liberado desde el tablero');

            return;
        }

        if (! $ticket->tecnico_id) {
            if (! $usuario->tieneRol(Rol::Tecnico)) {
                throw ValidationException::withMessages(['columna' => 'Asigna un técnico al ticket antes de moverlo (o usa "Autoasignar con IA").']);
            }
            $this->tickets->asignar($ticket, $usuario->id, $usuario, 'Tomado desde el tablero');
            $ticket->refresh();
        }

        $destino = match ($columna) {
            'asignado' => EstadoTicket::Asignado,
            'en_progreso' => EstadoTicket::EnProgreso,
            'en_espera' => EstadoTicket::EnEspera,
            'resuelto' => EstadoTicket::Resuelto,
        };
        // Desde "Resuelto" se reabre pasando por "En progreso"
        if ($ticket->estado === EstadoTicket::Resuelto && $destino !== EstadoTicket::EnProgreso) {
            $this->tickets->cambiarEstado($ticket, EstadoTicket::EnProgreso, $usuario);
        }
        // "Asignado" → "En espera"/"Resuelto" exige pasar antes por "En progreso"
        if ($ticket->estado === EstadoTicket::Asignado && $destino === EstadoTicket::Resuelto) {
            $this->tickets->cambiarEstado($ticket, EstadoTicket::EnProgreso, $usuario);
        }
        if ($ticket->estado !== $destino) {
            $this->tickets->cambiarEstado($ticket, $destino, $usuario, $solucion);
        }
    }

    /**
     * Asigna los tickets sin técnico al técnico activo con menor carga de su organización.
     *
     * @return Collection<int, array{ticket: string, tecnico: string}>
     */
    public function autoasignar(Usuario $usuario): Collection
    {
        $pendientes = Ticket::visiblesPara($usuario)
            ->where('estado', EstadoTicket::Abierto)->whereNull('tecnico_id')
            ->orderByRaw("case prioridad when 'critica' then 1 when 'alta' then 2 when 'media' then 3 else 4 end")
            ->orderBy('creado_en')->get();

        $resultado = collect();
        $cargas = [];
        foreach ($pendientes as $ticket) {
            $cargas[$ticket->organizacion_id] ??= $this->cargaTecnicos($ticket->organizacion_id);
            if ($cargas[$ticket->organizacion_id]->isEmpty()) {
                continue;
            }
            $elegido = $cargas[$ticket->organizacion_id]->sortBy([['carga', 'asc'], ['nombre', 'asc']])->first();
            $this->tickets->asignar($ticket, $elegido['id'], $usuario, 'Autoasignación por menor carga activa');
            $cargas[$ticket->organizacion_id] = $cargas[$ticket->organizacion_id]
                ->map(fn ($t) => $t['id'] === $elegido['id'] ? [...$t, 'carga' => $t['carga'] + 1] : $t);
            $resultado->push(['ticket' => $ticket->codigo, 'tecnico' => $elegido['nombre']]);
        }

        return $resultado;
    }

    /** @return Collection<int, array{id: int, nombre: string, carga: int}> */
    public function cargaTecnicos(int $organizacionId): Collection
    {
        return Usuario::sinFiltroOrganizacion()
            ->where('organizacion_id', $organizacionId)->where('estado', 'activo')
            ->whereHas('rolesOrganizacion', fn ($q) => $q->where('codigo', Rol::Tecnico->value))
            ->withCount(['ticketsAsignados as carga' => fn ($q) => $q->withoutGlobalScopes()
                ->whereIn('estado', [EstadoTicket::Abierto, EstadoTicket::Asignado, EstadoTicket::EnProgreso, EstadoTicket::EnEspera])])
            ->get()
            ->map(fn (Usuario $u) => ['id' => $u->id, 'nombre' => $u->nombre, 'carga' => (int) $u->carga]);
    }
}
