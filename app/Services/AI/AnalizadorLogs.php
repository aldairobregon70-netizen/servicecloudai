<?php

namespace App\Services\AI;

use App\Enums\NivelLog;
use App\Enums\SeveridadLog;
use App\Enums\TipoModeloIa;
use App\Models\AnalisisLogIa;
use App\Models\LogTecnico;
use App\Models\Usuario;
use App\Services\AI\Nlp\MotorReglas;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Análisis inteligente de logs: parsea "fecha NIVEL [origen] mensaje", detecta patrones
 * (timeouts, pool > 80 %, latencia > 3000 ms, reintentos, cola de espera, caché, memoria),
 * pide el diagnóstico a la IA y guarda logs_tecnicos + analisis_logs_ia + analisis_logs_detalle.
 */
class AnalizadorLogs
{
    public const MAX_LINEAS = 500;

    private const PATRON_LINEA = '/^\s*(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:[.,]\d+)?)\s+(DEBUG|INFO|NOTICE|WARN|WARNING|ERROR|CRITICAL|FATAL|ALERT)\s*(?:\[([^\]]+)\])?\s*(.*)$/i';

    public function __construct(private GestorIA $ia, private MotorReglas $motor) {}

    /** @return array<string, mixed> resultado listo para la vista (JSON) */
    public function analizar(string $texto, Usuario $usuario, ?int $organizacionId): array
    {
        $lineas = $this->parsear($texto);
        $patrones = $this->detectarPatrones($lineas);
        $eventos = $this->eventosDestacados($lineas, $patrones);
        $severidad = $this->severidad($patrones, $lineas);

        $solicitud = new SolicitudIA(
            tarea: SolicitudIA::ANALIZAR_LOGS,
            sistema: 'Eres un ingeniero SRE. Analiza los logs y los patrones detectados. Responde SOLO un JSON con: resumen (diagnóstico en 2-3 frases), '
                .'causas (lista de causas probables), acciones (lista de 3 a 6 recomendaciones concretas), confianza (0 a 1). En español.',
            usuario: 'Patrones detectados: '.json_encode($patrones, JSON_UNESCAPED_UNICODE)."\n\nLogs:\n".Str::limit(collect($lineas)->pluck('linea')->join("\n"), 6000),
            datos: ['patrones' => $patrones],
            json: true,
        );
        $respuesta = $this->ia->generar($solicitud);
        $diagnostico = $this->validar($respuesta->json(), $patrones);

        $analisis = $organizacionId ? $this->guardar($organizacionId, $lineas, $diagnostico, $severidad, $respuesta) : null;

        return [
            'analisis_id' => $analisis?->id,
            'severidad' => $severidad->value,
            'confianza' => round($diagnostico['confianza'] * 100),
            'conteos' => collect($lineas)->countBy(fn ($l) => $l['nivel']->value)->all(),
            'lineas' => count($lineas),
            'eventos' => $eventos,
            'diagnostico' => $diagnostico['resumen'],
            'causas' => $diagnostico['causas'],
            'recomendaciones' => $diagnostico['acciones'],
            'ticket' => $this->prefillTicket($patrones, $diagnostico, $severidad, $analisis),
        ];
    }

    /** @return list<array{fecha: CarbonImmutable, nivel: NivelLog, origen: ?string, mensaje: string, linea: string}> */
    public function parsear(string $texto): array
    {
        $resultado = [];
        foreach (array_slice(preg_split('/\R/', trim($texto)), 0, self::MAX_LINEAS) as $linea) {
            if (trim($linea) === '') {
                continue;
            }
            if (preg_match(self::PATRON_LINEA, $linea, $m)) {
                $nivel = match (strtoupper($m[2])) {
                    'DEBUG' => NivelLog::Debug,
                    'INFO', 'NOTICE' => NivelLog::Info,
                    'WARN', 'WARNING' => NivelLog::Warning,
                    'ERROR' => NivelLog::Error,
                    default => NivelLog::Critical,
                };
                $fecha = rescue(fn () => CarbonImmutable::parse(str_replace(',', '.', $m[1])), CarbonImmutable::now(), false);
                $resultado[] = ['fecha' => $fecha, 'nivel' => $nivel, 'origen' => $m[3] ?: null, 'mensaje' => trim($m[4]), 'linea' => trim($linea)];
            } else {
                // Línea sin formato: se toma como INFO para no perder contexto
                $resultado[] = ['fecha' => CarbonImmutable::now(), 'nivel' => preg_match('/error|exception|fail/i', $linea) ? NivelLog::Error : NivelLog::Info,
                    'origen' => null, 'mensaje' => trim($linea), 'linea' => trim($linea)];
            }
        }

        return $resultado;
    }

    /** @return array<string, mixed> */
    public function detectarPatrones(array $lineas): array
    {
        $p = ['timeout' => false, 'timeout_db' => false, 'pool' => null, 'pool_detalle' => null, 'latencia' => null, 'cola' => null, 'reintentos' => false, 'cache' => null, 'memoria' => false];
        foreach ($lineas as $l) {
            $msg = $l['mensaje'];
            if (preg_match('/time(d)?\s*out|tiempo de espera/i', $msg)) {
                $p['timeout'] = true;
                $p['timeout_db'] = $p['timeout_db'] || (bool) preg_match('/database|db|sql|postgres|mysql|connection/i', $msg);
            }
            if (preg_match('/active connections:?\s*(\d+)\s*\/\s*(\d+)/i', $msg, $m) && (int) $m[2] > 0) {
                $p['pool'] = max($p['pool'] ?? 0, (int) round($m[1] * 100 / $m[2]));
                $p['pool_detalle'] = "{$m[1]} de {$m[2]} conexiones se encuentran activas.";
            } elseif (preg_match('/pool[^0-9]*(\d{1,3})\s*%/i', $msg, $m)) {
                $p['pool'] = max($p['pool'] ?? 0, (int) $m[1]);
            }
            if (preg_match('/(\d{3,6})\s*ms/i', $msg, $m) && (int) $m[1] > 3000) {
                $p['latencia'] = max($p['latencia'] ?? 0, (int) $m[1]);
            }
            if (preg_match('/(?:wait|waiting)\s*queue(?:\s*length)?:?\s*(\d+)/i', $msg, $m)) {
                $p['cola'] = max($p['cola'] ?? 0, (int) $m[1]);
            }
            if (preg_match('/retr(y|ies)|reintento/i', $msg)) {
                $p['reintentos'] = true;
            }
            if (preg_match('/hit ratio:?\s*(\d{1,3})\s*%/i', $msg, $m)) {
                $p['cache'] = (int) $m[1];
            }
            if (preg_match('/out of memory|outofmemory|memoria insuficiente|oom/i', $msg)) {
                $p['memoria'] = true;
            }
        }

        return $p;
    }

    /** Eventos para la UI con las clases .level del mockup (critical / warning / info). */
    private function eventosDestacados(array $lineas, array $p): array
    {
        $eventos = [];
        if ($p['timeout']) {
            $linea = collect($lineas)->first(fn ($l) => preg_match('/time(d)?\s*out|tiempo de espera/i', $l['mensaje']));
            $eventos[] = ['nivel' => 'critical', 'etiqueta' => 'CRÍTICO', 'titulo' => $p['timeout_db'] ? 'Database connection timeout' : 'Timeout detectado',
                'detalle' => $p['timeout_db'] ? 'Conexión a base de datos agotó el tiempo de espera.' : Str::limit($linea['mensaje'] ?? '', 120)];
        }
        if ($p['reintentos']) {
            $eventos[] = ['nivel' => 'critical', 'etiqueta' => 'CRÍTICO', 'titulo' => 'Retry limit exceeded', 'detalle' => 'Se agotaron los reintentos de la operación.'];
        }
        if ($p['memoria']) {
            $eventos[] = ['nivel' => 'critical', 'etiqueta' => 'CRÍTICO', 'titulo' => 'Memoria insuficiente', 'detalle' => 'El proceso reportó falta de memoria.'];
        }
        if ($p['latencia']) {
            $eventos[] = ['nivel' => 'warning', 'etiqueta' => 'WARNING', 'titulo' => 'Response time > '.floor($p['latencia'] / 1000).' segundos', 'detalle' => "Se detectó una respuesta de {$p['latencia']}ms."];
        }
        if ($p['cola']) {
            $eventos[] = ['nivel' => 'warning', 'etiqueta' => 'WARNING', 'titulo' => 'Cola de espera elevada', 'detalle' => "{$p['cola']} solicitudes esperando conexión."];
        }
        if ($p['pool'] !== null) {
            $eventos[] = ['nivel' => 'info', 'etiqueta' => 'INFO', 'titulo' => "Connection pool usage {$p['pool']}%",
                'detalle' => ($p['pool_detalle'] ?? "Uso del pool de conexiones: {$p['pool']} %.").($p['pool'] >= 80 ? ' Supera el umbral del 80 %.' : '')];
        }
        if ($p['cache'] !== null && $p['cache'] < 70) {
            $eventos[] = ['nivel' => 'info', 'etiqueta' => 'INFO', 'titulo' => "Cache hit ratio {$p['cache']}%", 'detalle' => 'Baja efectividad de caché.'];
        }
        if (! $eventos) {
            foreach (collect($lineas)->filter(fn ($l) => in_array($l['nivel'], [NivelLog::Error, NivelLog::Critical], true))->take(3) as $l) {
                $eventos[] = ['nivel' => 'critical', 'etiqueta' => $l['nivel']->label(), 'titulo' => Str::limit($l['mensaje'], 80), 'detalle' => $l['origen'] ? 'Origen: '.$l['origen'] : ''];
            }
        }

        return $eventos;
    }

    private function severidad(array $p, array $lineas): SeveridadLog
    {
        $niveles = collect($lineas)->pluck('nivel');

        return match (true) {
            $p['timeout'] || $p['reintentos'] || $p['memoria'] || $niveles->contains(NivelLog::Critical) => SeveridadLog::Critical,
            $niveles->contains(NivelLog::Error) => SeveridadLog::Error,
            ($p['pool'] ?? 0) >= 80 || $p['latencia'] || $niveles->contains(NivelLog::Warning) => SeveridadLog::Warning,
            default => SeveridadLog::Info,
        };
    }

    private function validar(?array $datos, array $patrones): array
    {
        $local = $this->motor->diagnosticarLogs($patrones);
        if (! $datos) {
            return $local;
        }
        $lista = fn ($v) => is_array($v) ? array_values(array_filter($v, 'is_string')) : [];

        return [
            'resumen' => is_string($datos['resumen'] ?? null) && $datos['resumen'] !== '' ? $datos['resumen'] : $local['resumen'],
            'causas' => $lista($datos['causas'] ?? null) ?: $local['causas'],
            'acciones' => $lista($datos['acciones'] ?? null) ?: $local['acciones'],
            'confianza' => is_numeric($datos['confianza'] ?? null) ? max(0, min(1, (float) $datos['confianza'])) : $local['confianza'],
        ];
    }

    private function guardar(int $organizacionId, array $lineas, array $diagnostico, SeveridadLog $severidad, RespuestaIA $respuesta): AnalisisLogIa
    {
        return DB::transaction(function () use ($organizacionId, $lineas, $diagnostico, $severidad, $respuesta) {
            $ids = [];
            foreach ($lineas as $l) {
                $ids[] = LogTecnico::create([
                    'organizacion_id' => $organizacionId, 'origen' => $l['origen'] ? Str::limit($l['origen'], 175) : null,
                    'nivel' => $l['nivel'], 'mensaje' => $l['mensaje'], 'fecha_evento' => $l['fecha'],
                    'metadatos' => ['linea' => Str::limit($l['linea'], 1000)],
                ])->id;
            }
            $analisis = AnalisisLogIa::create([
                'organizacion_id' => $organizacionId,
                'modelo_id' => $this->ia->modeloId(TipoModeloIa::Logs, $respuesta),
                'resumen' => $diagnostico['resumen'],
                'posibles_causas' => $diagnostico['causas'],
                'acciones_recomendadas' => $diagnostico['acciones'],
                'severidad' => $severidad,
            ]);
            $analisis->logs()->attach($ids);

            return $analisis;
        });
    }

    private function prefillTicket(array $p, array $diagnostico, SeveridadLog $severidad, ?AnalisisLogIa $analisis): array
    {
        $titulo = match (true) {
            $p['timeout_db'] => 'Timeout de base de datos detectado por AI Log Analyzer',
            $p['timeout'] => 'Timeouts detectados por AI Log Analyzer',
            ($p['pool'] ?? 0) >= 80 => 'Saturación del pool de conexiones detectada por AI Log Analyzer',
            default => 'Anomalías detectadas por AI Log Analyzer',
        };

        return [
            'titulo' => $titulo,
            'descripcion' => $diagnostico['resumen']."\n\nRecomendaciones:\n- ".implode("\n- ", $diagnostico['acciones']),
            'impacto' => in_array($severidad, [SeveridadLog::Critical, SeveridadLog::Error], true) ? 'alto' : 'medio',
            'urgencia' => $severidad === SeveridadLog::Critical ? 'media' : 'baja',
            'categoria' => $p['timeout_db'] || $p['pool'] !== null ? 'Bases de datos' : 'Aplicaciones',
            'analisis' => $analisis?->id,
        ];
    }
}
