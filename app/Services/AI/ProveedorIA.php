<?php

namespace App\Services\AI;

interface ProveedorIA
{
    /** Identificador del proveedor: simulado | gemini. */
    public function nombre(): string;

    /** Nombre del modelo de texto y de embeddings en uso. */
    public function modeloTexto(): string;

    public function modeloEmbedding(): string;

    public function generar(SolicitudIA $solicitud): RespuestaIA;

    /**
     * @param  list<string>  $textos
     * @return list<list<float>> un vector normalizado por texto
     */
    public function embeddings(array $textos): array;
}
