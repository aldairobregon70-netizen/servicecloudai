<?php

namespace App\Enums;

/** notificaciones.tipo */
enum TipoNotificacion: string
{
    use ConEtiqueta;

    case Ticket = 'ticket';
    case Sla = 'sla';
    case Ia = 'ia';
    case Sistema = 'sistema';
    case Asignacion = 'asignacion';

    public function label(): string
    {
        return match ($this) {
            self::Ticket => 'Ticket',
            self::Sla => 'SLA',
            self::Ia => 'IA',
            self::Sistema => 'Sistema',
            self::Asignacion => 'Asignación',
        };
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::Ticket => 'blue',
            self::Sla => 'yellow',
            self::Ia => 'purple',
            self::Sistema => 'gray',
            self::Asignacion => 'cyan',
        };
    }
}
