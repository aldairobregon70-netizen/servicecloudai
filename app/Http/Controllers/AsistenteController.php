<?php

namespace App\Http\Controllers;

use App\Enums\RolMensajeIa;
use App\Models\ConversacionIa;
use App\Models\DocumentoRag;
use App\Models\FragmentoRag;
use App\Services\AI\AsistenteChat;
use App\Services\AI\GestorIA;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AsistenteController extends Controller
{
    public function index(Request $request, GestorIA $ia): View
    {
        $usuario = $request->user();
        $conversacion = $request->boolean('nueva') ? null
            : ConversacionIa::where('usuario_id', $usuario->id)->latest('creado_en')->with('mensajes')->first();

        // Fragmentos indexados accesibles (públicos + de la organización)
        $documentos = DocumentoRag::where('estado', 'indexado')->pluck('id');

        return view('asistente.index', [
            'conversacion' => $conversacion,
            'mensajes' => $conversacion?->mensajes->whereIn('rol', [RolMensajeIa::User, RolMensajeIa::Assistant])->sortBy('id')->values() ?? collect(),
            'prompt' => (string) $request->query('prompt', ''),
            'fragmentos' => FragmentoRag::whereIn('documento_id', $documentos)->count(),
            'proveedor' => $ia->proveedor()->nombre(),
        ]);
    }

    /** POST /asistente/mensajes (JSON, throttle:ia). */
    public function enviar(Request $request, AsistenteChat $asistente, GestorIA $ia): JsonResponse
    {
        $datos = $request->validate([
            'mensaje' => ['required', 'string', 'max:2000'],
            'conversacion_id' => ['nullable', 'integer'],
        ], [], ['mensaje' => 'mensaje']);

        $conversacion = isset($datos['conversacion_id'])
            ? ConversacionIa::where('usuario_id', $request->user()->id)->find($datos['conversacion_id'])
            : null;

        $respuesta = $asistente->responder($request->user(), $datos['mensaje'], $conversacion);

        return response()->json([
            'html' => $respuesta['html'],
            'conversacion_id' => $respuesta['conversacion']?->id,
            'aviso' => $ia->huboFallback() ? 'El proveedor externo no respondió; se usó el motor local.' : null,
        ]);
    }
}
