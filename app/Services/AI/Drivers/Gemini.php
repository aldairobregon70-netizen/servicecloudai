<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Nlp\Texto;
use App\Services\AI\ProveedorIA;
use App\Services\AI\RespuestaIA;
use App\Services\AI\SolicitudIA;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Driver Gemini (API REST de Google AI). Solo recibe el texto del prompt: los datos
 * estructurados de SolicitudIA nunca salen del servidor. Correos y teléfonos se enmascaran.
 */
class Gemini implements ProveedorIA
{
    public function __construct(private array $config) {}

    public function nombre(): string
    {
        return 'gemini';
    }

    public function modeloTexto(): string
    {
        return (string) ($this->config['modelo_texto'] ?? '');
    }

    public function modeloEmbedding(): string
    {
        return (string) ($this->config['modelo_embedding'] ?? '');
    }

    public function disponible(): bool
    {
        return filled($this->config['api_key'] ?? null) && filled($this->modeloTexto());
    }

    public function generar(SolicitudIA $solicitud): RespuestaIA
    {
        $this->exigirConfiguracion($this->modeloTexto());

        $respuesta = $this->cliente()->post($this->url($this->modeloTexto(), 'generateContent'), [
            'systemInstruction' => ['parts' => [['text' => self::anonimizar($solicitud->sistema)]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => self::anonimizar($solicitud->usuario)]]]],
            'generationConfig' => [
                'temperature' => 0.2,
                'responseMimeType' => $solicitud->json ? 'application/json' : 'text/plain',
            ],
        ]);

        if (! $respuesta->successful()) {
            throw new RuntimeException('Gemini respondió '.$respuesta->status());
        }
        $texto = $respuesta->json('candidates.0.content.parts.0.text');
        if (! is_string($texto) || $texto === '') {
            throw new RuntimeException('Gemini no devolvió contenido.');
        }

        return new RespuestaIA(
            texto: $texto,
            proveedor: $this->nombre(),
            modelo: $this->modeloTexto(),
            tokensEntrada: (int) $respuesta->json('usageMetadata.promptTokenCount', Texto::estimarTokens($solicitud->usuario)),
            tokensSalida: (int) $respuesta->json('usageMetadata.candidatesTokenCount', Texto::estimarTokens($texto)),
        );
    }

    public function embeddings(array $textos): array
    {
        $modelo = $this->modeloEmbedding();
        $this->exigirConfiguracion($modelo);
        $vectores = [];

        foreach (array_chunk($textos, 50) as $lote) {
            $respuesta = $this->cliente()->post($this->url($modelo, 'batchEmbedContents'), [
                'requests' => array_map(fn ($t) => ['model' => 'models/'.$modelo, 'content' => ['parts' => [['text' => self::anonimizar($t)]]]], $lote),
            ]);
            if (! $respuesta->successful()) {
                throw new RuntimeException('Gemini (embeddings) respondió '.$respuesta->status());
            }
            foreach ($respuesta->json('embeddings', []) as $embedding) {
                $vectores[] = Texto::normalizarVector(array_map('floatval', $embedding['values'] ?? []));
            }
        }
        if (count($vectores) !== count($textos)) {
            throw new RuntimeException('Gemini devolvió un número inesperado de embeddings.');
        }

        return $vectores;
    }

    /** Quita correos y teléfonos antes de enviar texto a un proveedor externo. */
    public static function anonimizar(string $texto): string
    {
        $texto = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[correo]', $texto);

        return preg_replace('/\+?\d[\d\s-]{7,}\d/', '[teléfono]', $texto);
    }

    private function cliente(): PendingRequest
    {
        return Http::timeout((int) ($this->config['timeout'] ?? 20))
            ->acceptJson()
            ->withHeaders(['x-goog-api-key' => (string) $this->config['api_key']]);
    }

    private function url(string $modelo, string $metodo): string
    {
        return rtrim((string) $this->config['url_base'], '/')."/models/{$modelo}:{$metodo}";
    }

    private function exigirConfiguracion(string $modelo): void
    {
        if (blank($this->config['api_key'] ?? null) || blank($modelo)) {
            throw new RuntimeException('Gemini no está configurado (GEMINI_API_KEY / modelo).');
        }
    }
}
