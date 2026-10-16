<?php

namespace App\Services\AI;

final class RespuestaIA
{
    public function __construct(
        public readonly string $texto,
        public readonly string $proveedor,
        public readonly string $modelo,
        public readonly int $tokensEntrada = 0,
        public readonly int $tokensSalida = 0,
    ) {}

    /** Decodifica la respuesta JSON (acepta bloques ```json ... ```). */
    public function json(): ?array
    {
        $texto = trim($this->texto);
        if (preg_match('/```(?:json)?\s*(.+?)\s*```/s', $texto, $m)) {
            $texto = $m[1];
        }
        $datos = json_decode($texto, true);

        return is_array($datos) ? $datos : null;
    }
}
