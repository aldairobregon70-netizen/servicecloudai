<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Nlp\MotorReglas;
use App\Services\AI\Nlp\Texto;
use App\Services\AI\ProveedorIA;
use App\Services\AI\RespuestaIA;
use App\Services\AI\SolicitudIA;

/** Driver por defecto: reglas y embeddings locales, sin internet, determinista. */
class Simulado implements ProveedorIA
{
    public function __construct(private MotorReglas $motor) {}

    public function nombre(): string
    {
        return 'simulado';
    }

    public function modeloTexto(): string
    {
        return 'servicecloud-reglas-1.0';
    }

    public function modeloEmbedding(): string
    {
        return 'bolsa-palabras-'.Texto::DIMENSIONES;
    }

    public function generar(SolicitudIA $solicitud): RespuestaIA
    {
        $d = $solicitud->datos;
        $texto = match ($solicitud->tarea) {
            SolicitudIA::CLASIFICAR => json_encode(
                $this->motor->clasificar($d['titulo'] ?? '', $d['descripcion'] ?? '', $d['impacto'] ?? null, $d['urgencia'] ?? null),
                JSON_UNESCAPED_UNICODE,
            ),
            SolicitudIA::ANALIZAR_LOGS => json_encode($this->motor->diagnosticarLogs($d['patrones'] ?? []), JSON_UNESCAPED_UNICODE),
            SolicitudIA::RESPONDER => $this->motor->responder($d),
            default => '',
        };

        return new RespuestaIA(
            texto: $texto,
            proveedor: $this->nombre(),
            modelo: $this->modeloTexto(),
            tokensEntrada: Texto::estimarTokens($solicitud->sistema.$solicitud->usuario),
            tokensSalida: Texto::estimarTokens($texto),
        );
    }

    public function embeddings(array $textos): array
    {
        return array_map(fn (string $t) => Texto::embedding($t), $textos);
    }
}
