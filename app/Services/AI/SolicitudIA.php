<?php

namespace App\Services\AI;

/**
 * Petición a un proveedor de IA.
 * - sistema/usuario: texto del prompt (sin datos personales; es lo único que viaja a un proveedor externo).
 * - datos: contexto estructurado para el motor local (driver simulado); nunca se envía fuera.
 */
final class SolicitudIA
{
    public const CLASIFICAR = 'clasificar';

    public const RESPONDER = 'responder';

    public const ANALIZAR_LOGS = 'analizar_logs';

    public function __construct(
        public readonly string $tarea,
        public readonly string $sistema,
        public readonly string $usuario,
        public readonly array $datos = [],
        public readonly bool $json = false,
    ) {}
}
