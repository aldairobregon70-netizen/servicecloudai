<?php

namespace App\Enums;

/** tickets.estado */
enum EstadoTicket: string
{
    use ConEtiqueta;

    case Abierto = 'abierto';
    case Asignado = 'asignado';
    case EnProgreso = 'en_progreso';
    case EnEspera = 'en_espera';
    case Resuelto = 'resuelto';
    case Cerrado = 'cerrado';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Abierto => 'Abierto',
            self::Asignado => 'Asignado',
            self::EnProgreso => 'En progreso',
            self::EnEspera => 'En espera',
            self::Resuelto => 'Resuelto',
            self::Cerrado => 'Cerrado',
            self::Cancelado => 'Cancelado',
        };
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::Abierto => 'blue',
            self::Asignado => 'blue',
            self::EnProgreso => 'purple',
            self::EnEspera => 'yellow',
            self::Resuelto => 'green',
            self::Cerrado => 'green',
            self::Cancelado => 'gray',
        };
    }
}
