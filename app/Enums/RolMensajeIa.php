<?php

namespace App\Enums;

/** mensajes_ia.rol */
enum RolMensajeIa: string
{
    use ConEtiqueta;

    case System = 'system';
    case User = 'user';
    case Assistant = 'assistant';
    case Tool = 'tool';

    public function label(): string
    {
        return match ($this) {
            self::System => 'Sistema',
            self::User => 'Usuario',
            self::Assistant => 'Asistente',
            self::Tool => 'Herramienta',
        };
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::System => 'gray',
            self::User => 'blue',
            self::Assistant => 'purple',
            self::Tool => 'cyan',
        };
    }
}
