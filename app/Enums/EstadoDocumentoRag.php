<?php

namespace App\Enums;

/** documentos_rag.estado */
enum EstadoDocumentoRag: string
{
    use ConEtiqueta;

    case Pendiente = 'pendiente';
    case Indexado = 'indexado';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Indexado => 'Indexado',
            self::Error => 'Error',
        };
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::Pendiente => 'yellow',
            self::Indexado => 'green',
            self::Error => 'red',
        };
    }
}
