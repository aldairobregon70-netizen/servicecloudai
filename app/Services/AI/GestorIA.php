<?php

namespace App\Services\AI;

use App\Enums\TipoModeloIa;
use App\Models\ModeloIa;
use App\Services\AI\Drivers\Gemini;
use App\Services\AI\Drivers\Simulado;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Punto de entrada a la IA: usa el driver de AI_DRIVER y, si Gemini falla o no tiene
 * clave, responde con el simulado y deja constancia (para avisar con un toast discreto).
 */
class GestorIA
{
    private bool $huboFallback = false;

    public function __construct(private Simulado $simulado, private ?Gemini $gemini = null) {}

    public static function crear(): self
    {
        $config = config('servicecloud.ia');
        $gemini = ($config['driver'] ?? 'simulado') === 'gemini' ? new Gemini($config['gemini'] ?? []) : null;

        return new self(app(Simulado::class), $gemini);
    }

    /** Proveedor principal configurado. */
    public function proveedor(): ProveedorIA
    {
        return $this->gemini ?? $this->simulado;
    }

    public function huboFallback(): bool
    {
        return $this->huboFallback;
    }

    public function generar(SolicitudIA $solicitud): RespuestaIA
    {
        if ($this->gemini) {
            try {
                return $this->gemini->generar($solicitud);
            } catch (Throwable $e) {
                $this->registrarFallback('generar', $e);
            }
        }

        return $this->simulado->generar($solicitud);
    }

    /**
     * Embeddings y el nombre del modelo que los generó (para comparar solo vectores del mismo modelo).
     *
     * @return array{vectores: list<list<float>>, modelo: string}
     */
    public function embeddings(array $textos): array
    {
        if ($this->gemini && filled($this->gemini->modeloEmbedding())) {
            try {
                return ['vectores' => $this->gemini->embeddings($textos), 'modelo' => 'gemini:'.$this->gemini->modeloEmbedding()];
            } catch (Throwable $e) {
                $this->registrarFallback('embeddings', $e);
            }
        }

        return ['vectores' => $this->simulado->embeddings($textos), 'modelo' => 'simulado:'.$this->simulado->modeloEmbedding()];
    }

    /** Id en modelos_ia del modelo que respondió (se registra el de Gemini la primera vez). */
    public function modeloId(TipoModeloIa $tipo, ?RespuestaIA $respuesta = null): ?int
    {
        $proveedor = $respuesta?->proveedor ?? $this->proveedor()->nombre();
        if ($proveedor === 'gemini') {
            return ModeloIa::firstOrCreate(
                ['proveedor' => 'gemini', 'tipo' => $tipo, 'version' => $respuesta?->modelo ?? $this->gemini?->modeloTexto()],
                ['nombre' => 'Gemini '.($respuesta?->modelo ?? $this->gemini?->modeloTexto()), 'activo' => true],
            )->id;
        }

        return ModeloIa::where('proveedor', 'simulado')->where('tipo', $tipo)->value('id');
    }

    private function registrarFallback(string $operacion, Throwable $e): void
    {
        $this->huboFallback = true;
        Log::warning("IA: Gemini falló en {$operacion}; se usa el driver simulado.", ['error' => $e->getMessage()]);
        if (app()->runningInConsole() === false && session()->isStarted()) {
            session()->flash('aviso', ['IA en modo simulado', 'El proveedor externo no respondió; se usó el motor local.']);
        }
    }
}
