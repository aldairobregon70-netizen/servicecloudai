<?php

namespace App\Enums;

/** modelos_ia.tipo */
enum TipoModeloIa: string
{
    use ConEtiqueta;

    case Nlp = 'nlp';
    case Clasificacion = 'clasificacion';
    case Generativo = 'generativo';
    case Embedding = 'embedding';
    case Logs = 'logs';

    public function label(): string
    {
        return match ($this) {
            self::Nlp => 'NLP',
            self::Clasificacion => 'Clasificación',
            self::Generativo => 'Generativo',
            self::Embedding => 'Embedding',
            self::Logs => 'Logs',
        };
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::Nlp => 'blue',
            self::Clasificacion => 'purple',
            self::Generativo => 'cyan',
            self::Embedding => 'blue',
            self::Logs => 'yellow',
        };
    }
}
