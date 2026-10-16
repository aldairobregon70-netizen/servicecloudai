<?php

namespace App\Enums;

/** logs_tecnicos.nivel */
enum NivelLog: string
{
    use ConEtiqueta;

    case Debug = 'debug';
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Debug => 'DEBUG',
            self::Info => 'INFO',
            self::Warning => 'WARN',
            self::Error => 'ERROR',
            self::Critical => 'CRITICAL',
        };
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::Debug => 'gray',
            self::Info => 'blue',
            self::Warning => 'yellow',
            self::Error => 'red',
            self::Critical => 'red',
        };
    }
}
