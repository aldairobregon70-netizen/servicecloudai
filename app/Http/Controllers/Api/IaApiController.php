<?php

namespace App\Http\Controllers\Api;

use App\Enums\Impacto;
use App\Enums\Urgencia;
use App\Http\Controllers\Controller;
use App\Services\AI\GestorIA;
use App\Services\AI\Nlp\MotorReglas;
use App\Services\AI\RecomendadorRag;
use App\Services\AI\SolicitudIA;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Clasificación de texto sin crear un ticket (útil para pruebas de rendimiento del motor de IA
 * y del RAG en cada nube). No persiste nada.
 */
class IaApiController extends Controller
{
    public function clasificar(Request $request, GestorIA $ia, RecomendadorRag $rag, MotorReglas $motor): JsonResponse
    {
        $datos = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:10000'],
            'impacto' => ['nullable', Rule::in(Impacto::valores())],
            'urgencia' => ['nullable', Rule::in(Urgencia::valores())],
        ]);
        $descripcion = $datos['descripcion'] ?? $datos['titulo'];
        $inicio = hrtime(true);

        $respuesta = $ia->generar(new SolicitudIA(
            tarea: SolicitudIA::CLASIFICAR,
            sistema: 'Clasifica el ticket ITSM. Responde SOLO JSON con clasificacion, categoria, prioridad (baja|media|alta|critica), confianza (0-1), explicacion, causas, pasos, consulta_rag.',
            usuario: "Título: {$datos['titulo']}\nDescripción: {$descripcion}",
            datos: ['titulo' => $datos['titulo'], 'descripcion' => $descripcion, 'impacto' => $datos['impacto'] ?? null, 'urgencia' => $datos['urgencia'] ?? null],
            json: true,
        ));
        $resultado = $respuesta->json() ?? $motor->clasificar($datos['titulo'], $descripcion);
        $fuentes = $rag->buscar(($resultado['consulta_rag'] ?? '').' '.$datos['titulo'].' '.$descripcion, $request->user()->organizacion_id, $request->user(), 3);

        return response()->json([
            'clasificacion' => $resultado['clasificacion'] ?? null,
            'categoria' => $resultado['categoria'] ?? null,
            'prioridad' => $resultado['prioridad'] ?? null,
            'confianza' => $resultado['confianza'] ?? null,
            'causas' => $resultado['causas'] ?? [],
            'articulos' => $fuentes->map(fn ($f) => ['codigo' => $f['articulo']->codigo, 'titulo' => $f['articulo']->titulo, 'relevancia' => $f['relevancia']])->values(),
            'proveedor' => $respuesta->proveedor,
            'duracion_ms' => round((hrtime(true) - $inicio) / 1e6, 1),
        ]);
    }
}
