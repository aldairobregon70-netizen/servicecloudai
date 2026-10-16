<?php

namespace App\Enums;

/** analisis_logs_ia.severidad */
enum SeveridadLog: string
{
    use ConEtiqueta;

    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Informativa',
            self::Warning => 'Advertencia',
            self::Error => 'Error',
            self::Critical => 'Crítica',
        };
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::Info => 'blue',
            self::Warning => 'yellow',
            self::Error => 'red',
            self::Critical => 'red',
        };
    }
}
