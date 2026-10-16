<?php

namespace App\Http\Controllers;

use App\Models\AnalisisLogIa;
use App\Models\Organizacion;
use App\Services\AI\AnalizadorLogs;
use App\Services\AI\GestorIA;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnalisisLogController extends Controller
{
    /** Mismo ejemplo precargado que renderLogs() del mockup. */
    public const EJEMPLO = "2026-10-03 21:14:12 ERROR [api] Database connection timeout to crm-db.company.com\n"
        ."2026-10-03 21:14:13 WARN  [service] Response time detected (5230ms)\n"
        ."2026-10-03 21:14:14 INFO  [pool] Active connections: 92 / 100\n"
        ."2026-10-03 21:14:15 WARN  [pool] Wait queue length: 18\n"
        ."2026-10-03 21:14:16 INFO  [cache] Cache hit ratio: 61%\n"
        .'2026-10-03 21:14:18 ERROR [api] Retry limit exceeded for customer-query';

    public function index(Request $request): View
    {
        $usuario = $request->user();

        return view('logs.index', [
            'ejemplo' => self::EJEMPLO,
            'organizaciones' => $usuario->esAdminSaas() ? Organizacion::orderBy('razon_social')->pluck('razon_social', 'id')->all() : [],
            'recientes' => AnalisisLogIa::latest('creado_en')->with('organizacion:id,razon_social')->limit(5)->get(),
        ]);
    }

    /** POST /logs/analizar (JSON, throttle:ia). */
    public function analizar(Request $request, AnalizadorLogs $analizador, GestorIA $ia): JsonResponse
    {
        $usuario = $request->user();
        $datos = $request->validate([
            'logs' => ['required', 'string', 'max:100000'],
            'organizacion_id' => $usuario->esAdminSaas() ? ['nullable', 'integer', Rule::exists('organizaciones', 'id')] : ['prohibited'],
        ], ['logs.required' => 'Pega al menos una línea antes de analizar.']);

        $organizacionId = $usuario->esAdminSaas() ? ($datos['organizacion_id'] ?? null) : $usuario->organizacion_id;
        $resultado = $analizador->analizar($datos['logs'], $usuario, $organizacionId);
        $resultado['aviso'] = $ia->huboFallback() ? 'El proveedor externo no respondió; se usó el motor local.' : null;
        $resultado['ticket_url'] = route('tickets.create', array_filter([
            'titulo' => $resultado['ticket']['titulo'],
            'descripcion' => $resultado['ticket']['descripcion'],
            'impacto' => $resultado['ticket']['impacto'],
            'urgencia' => $resultado['ticket']['urgencia'],
            'categoria' => $resultado['ticket']['categoria'],
            'analisis' => $resultado['ticket']['analisis'],
        ]));

        return response()->json($resultado);
    }
}
