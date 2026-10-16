<?php

namespace App\Services\AI\Nlp;

use App\Enums\Impacto;
use App\Enums\Prioridad;
use App\Enums\Urgencia;

/**
 * Motor local de reglas del driver "simulado": clasificación por palabras clave
 * ponderadas, prioridad por matriz impacto × urgencia ajustada por términos de severidad,
 * respuestas del asistente y diagnóstico de logs. Todo determinista y sin internet.
 */
final class MotorReglas
{
    /** Categorías de tickets con palabras clave (texto normalizado, sin tildes) y su peso. */
    public const CATEGORIAS = [
        'Aplicaciones' => ['aplicacion' => 2, 'sistema' => 1, 'erp' => 2, 'crm' => 2, 'modulo' => 1, 'portal' => 1, 'app ' => 1,
            'consultar' => 1, 'consulta de cliente' => 2, 'reporte' => 1, 'pedido' => 1, 'factur' => 1, 'pantalla' => 0.5],
        'Bases de datos' => ['base de datos' => 3, 'sql' => 3, 'query' => 2, 'indice' => 2, 'tabla' => 1, 'postgres' => 3, 'oracle' => 3,
            'mysql' => 3, 'consulta lenta' => 2, 'pool' => 1.5, 'deadlock' => 3],
        'Redes' => ['vpn' => 3, 'red ' => 2, 'internet' => 2, 'wifi' => 3, 'wi-fi' => 3, 'conexion' => 1, 'desconecta' => 2, 'paquete' => 2,
            'switch' => 2, 'router' => 2, 'dns' => 2, 'enlace' => 1.5, 'firewall' => 2, 'latencia de red' => 2],
        'Accesos' => ['acceso' => 2, 'contrasena' => 3, 'password' => 3, 'permiso' => 2, 'bloquead' => 2, 'login' => 2, 'iniciar sesion' => 1.5,
            'carpeta compartida' => 2, 'credencial' => 2, 'alta de usuario' => 3, 'restablecer' => 2],
        'Software' => ['instal' => 3, 'licencia' => 2, 'office' => 2, 'actualiz' => 1.5, 'programa' => 1.5, 'onedrive' => 2, 'outlook' => 2,
            'excel' => 1.5, 'sincroniz' => 1.5],
        'Infraestructura' => ['servidor' => 2, 'disco' => 2, 'cpu' => 2, 'memoria' => 2, 'respaldo' => 2, 'backup' => 2, 'espacio' => 1.5,
            'almacenamiento' => 2, 'virtualiz' => 1.5],
        'Hardware' => ['impresora' => 3, 'laptop' => 2, 'teclado' => 2, 'mouse' => 2, 'monitor' => 1.5, 'no enciende' => 3, 'bateria' => 2,
            'atasca' => 2, 'toner' => 2],
        'Seguridad' => ['phishing' => 3, 'virus' => 3, 'malware' => 3, 'sospechos' => 2, 'ataque' => 3, 'intrusion' => 3, 'intentos fallidos' => 3,
            'ransomware' => 3, 'anomal' => 2, 'acceso no autorizado' => 3],
        'Cloud' => ['aws' => 3, 'azure' => 3, 'gcp' => 3, 'nube' => 2, 'cloud' => 2, 'instancia' => 1.5, 'costo' => 1.5, 'kubernetes' => 2],
    ];

    /** Tipo de incidente (lo que el mockup llama "Clasificación"). */
    private const CLASIFICACIONES = [
        'Incidente de rendimiento' => ['demor', 'lento', 'lentitud', 'tarda', 'latencia', 'timeout', 'tiempo de espera', 'no responde', 'se cuelga', 'congela', 'saturad', 'alto consumo'],
        'Incidente de disponibilidad' => ['caido', 'caida', 'no funciona', 'no carga', 'no abre', 'interrupcion', 'fuera de servicio', 'sin servicio', 'no puedo acceder', 'no conecta', 'desconecta', 'sin conexion'],
        'Solicitud de servicio' => ['solicito', 'solicitud', 'necesito', 'requiero', 'instalar', 'instalacion', 'alta de', 'nuevo acceso', 'permiso para', 'restablecer'],
        'Consulta' => ['como puedo', 'consulta sobre', 'duda', 'pregunta', 'informacion sobre'],
    ];

    /** Términos que suben la prioridad sugerida. */
    private const SEVERIDAD = [
        'error' => 1, 'falla' => 1, 'demor' => 1, 'lento' => 1, 'demasiado' => 0.5, 'no funciona' => 2, 'caido' => 2.5, 'caida' => 2.5,
        'todos' => 1.5, 'toda la' => 1, 'nadie' => 2, 'produccion' => 1.5, 'cliente' => 1, 'urgente' => 1.5, 'critico' => 2,
        'perdida de datos' => 3, 'ventas' => 1, 'factur' => 1, 'bloquea' => 1.5, 'varios usuarios' => 1.5, 'sospechos' => 1.5,
    ];

    /** Causas probables y pasos por tipo / categoría. */
    private const CONOCIMIENTO = [
        'Incidente de rendimiento' => [
            'causas' => ['consulta SQL lenta', 'saturación del pool de conexiones', 'latencia de red', 'recursos insuficientes', 'dependencia externa lenta'],
            'pasos' => ['Revisar el tiempo de ejecución de la consulta afectada con EXPLAIN ANALYZE.', 'Medir conexiones activas y en espera del pool.', 'Revisar métricas de CPU y RAM del servidor de aplicaciones y de base de datos.', 'Medir la latencia hacia dependencias externas.', 'Aplicar índices o paginación y validar el tiempo de respuesta.'],
            'rag' => 'consulta sql lenta indice plan ejecucion pool conexiones timeout latencia rendimiento recursos',
        ],
        'Redes' => [
            'causas' => ['MTU o fragmentación en el túnel', 'pérdida de paquetes en el enlace principal', 'expiración de sesión o reautenticación MFA', 'cliente VPN desactualizado', 'conflicto de rutas o DNS'],
            'pasos' => ['Confirmar si afecta a uno o varios usuarios.', 'Revisar los logs del concentrador VPN en el horario reportado.', 'Medir pérdida de paquetes con mtr/pathping.', 'Probar con MTU 1400 y MSS clamping.', 'Actualizar el cliente VPN y validar rutas/DNS.'],
            'rag' => 'vpn desconexion mtu paquetes enlace red dns ruta',
        ],
        'Accesos' => [
            'causas' => ['permisos de grupo o carpeta no asignados', 'cuenta bloqueada por intentos fallidos', 'contraseña expirada', 'MFA no configurado'],
            'pasos' => ['Verificar el estado de la cuenta en el directorio.', 'Revisar la pertenencia a grupos y permisos de la carpeta.', 'Restablecer la contraseña o desbloquear la cuenta.', 'Confirmar el acceso con el usuario.'],
            'rag' => 'acceso permiso cuenta bloqueada contrasena seguridad',
        ],
        'Software' => [
            'causas' => ['licencia no disponible', 'incompatibilidad de versión', 'permisos de instalación insuficientes', 'perfil de usuario dañado'],
            'pasos' => ['Validar la licencia y la versión requerida.', 'Instalar con privilegios administrativos.', 'Verificar la compatibilidad con el sistema operativo.', 'Confirmar el funcionamiento con el usuario.'],
            'rag' => 'instalacion software licencia actualizacion',
        ],
        'Infraestructura' => [
            'causas' => ['volumen de datos cerca del límite', 'logs o archivos temporales sin rotación', 'respaldos locales acumulados', 'recursos del servidor saturados'],
            'pasos' => ['Identificar los directorios que más crecen.', 'Aplicar rotación de logs y mover respaldos a almacenamiento externo.', 'Revisar la salud del disco (SMART) y la latencia de IOPS.', 'Planificar la ampliación del volumen si el crecimiento es legítimo.'],
            'rag' => 'disco espacio almacenamiento smart iops servidor respaldo',
        ],
        'Hardware' => [
            'causas' => ['falla física del equipo', 'controlador desactualizado', 'consumibles agotados o atascos', 'fuente de energía defectuosa'],
            'pasos' => ['Revisar el estado físico y las conexiones.', 'Actualizar o reinstalar controladores.', 'Probar con otro equipo o periférico.', 'Gestionar el reemplazo si la falla persiste.'],
            'rag' => 'hardware disco smart diagnostico equipo',
        ],
        'Seguridad' => [
            'causas' => ['credenciales comprometidas', 'correo de phishing', 'malware en el equipo', 'privilegios excesivos'],
            'pasos' => ['Contener: bloquear la cuenta o aislar el equipo sin apagarlo.', 'Preservar evidencias (logs de autenticación, firewall).', 'Escalar al SOC y notificar al responsable.', 'Documentar la causa raíz y las medidas preventivas.'],
            'rag' => 'seguridad acceso anomalo phishing contencion evidencias soc',
        ],
        'Cloud' => [
            'causas' => ['límites o throttling del proveedor', 'servicios en distinta región o zona', 'créditos de CPU agotados', 'configuración de escalado insuficiente'],
            'pasos' => ['Revisar métricas y alarmas del proveedor.', 'Verificar región/zona de los servicios relacionados.', 'Ajustar el escalado o el tamaño de instancia.', 'Configurar alarmas por percentil 95.'],
            'rag' => 'cloud latencia region instancia escalado cache',
        ],
        'general' => [
            'causas' => ['cambio de configuración reciente', 'actualización de software', 'dependencia de un servicio relacionado'],
            'pasos' => ['Reproducir el problema y registrar evidencias.', 'Revisar cambios recientes en el servicio.', 'Consultar la base de conocimiento por síntomas similares.', 'Escalar al segundo nivel si no se resuelve.'],
            'rag' => '',
        ],
    ];

    // ------------------------------------------------------------------ clasificación

    /**
     * @return array{clasificacion: string, categoria: string, prioridad: string, confianza: float, explicacion: string,
     *               causas: list<string>, pasos: list<string>, consulta_rag: string, terminos: list<string>}
     */
    public function clasificar(string $titulo, string $descripcion, ?string $impacto = null, ?string $urgencia = null): array
    {
        $texto = Texto::normalizar($titulo.' '.$titulo.' '.$descripcion); // el título pesa doble

        // Categoría
        $puntajes = [];
        $terminos = [];
        foreach (self::CATEGORIAS as $categoria => $palabras) {
            $puntajes[$categoria] = 0.0;
            foreach ($palabras as $palabra => $peso) {
                if (Texto::contiene($texto, trim($palabra))) {
                    $puntajes[$categoria] += $peso;
                    $terminos[] = trim($palabra);
                }
            }
        }
        arsort($puntajes);
        $categorias = array_keys($puntajes);
        [$top, $segundo] = [$puntajes[$categorias[0]], $puntajes[$categorias[1]]];

        // Tipo de incidente
        $clasificacion = null;
        foreach (self::CLASIFICACIONES as $tipo => $palabras) {
            foreach ($palabras as $palabra) {
                if (Texto::contiene($texto, $palabra)) {
                    $clasificacion = $tipo;
                    $terminos[] = $palabra;
                    break 2;
                }
            }
        }
        if ($puntajes['Seguridad'] >= 3) {
            $clasificacion = 'Incidente de seguridad';
        }
        $categoria = $top > 0 ? $categorias[0] : ($clasificacion === 'Incidente de rendimiento' ? 'Aplicaciones' : 'Aplicaciones');
        $clasificacion ??= $top > 0 ? 'Incidente de '.mb_strtolower($categoria) : 'Incidente general';

        // Prioridad: matriz impacto × urgencia ajustada por severidad del texto
        $base = ($impacto && $urgencia)
            ? Prioridad::desdeMatriz(Impacto::from($impacto), Urgencia::from($urgencia))
            : Prioridad::Media;
        $severidad = 0.0;
        foreach (self::SEVERIDAD as $palabra => $peso) {
            if (Texto::contiene($texto, $palabra)) {
                $severidad += $peso;
            }
        }
        $porTexto = match (true) {
            $severidad >= 5 => Prioridad::Critica,
            $severidad >= 2.5 => Prioridad::Alta,
            $severidad >= 1 => Prioridad::Media,
            default => Prioridad::Baja,
        };
        $prioridad = $porTexto->peso() > $base->peso() ? $porTexto : $base;
        if ($clasificacion === 'Solicitud de servicio' && $severidad < 2.5) {
            $prioridad = $base->peso() > Prioridad::Media->peso() ? Prioridad::Media : $base;
        }

        // Confianza: fuerza de la categoría ganadora y distancia con la segunda
        $confianza = 0.6 + 0.3 * min(1, $top / 3) + (isset(self::CLASIFICACIONES[$clasificacion]) ? 0.05 : 0) - 0.2 * ($top + $segundo > 0 ? $segundo / ($top + $segundo) : 0);
        $confianza = round(max(0.55, min(0.97, $top > 0 ? $confianza : 0.58)), 2);

        $base = self::CONOCIMIENTO[$clasificacion] ?? self::CONOCIMIENTO[$categoria] ?? self::CONOCIMIENTO['general'];
        $terminos = array_values(array_unique($terminos));

        return [
            'clasificacion' => $clasificacion,
            'categoria' => $categoria,
            'prioridad' => $prioridad->value,
            'confianza' => $confianza,
            'explicacion' => $clasificacion.': términos detectados '.($terminos ? '"'.implode('", "', array_slice($terminos, 0, 5)).'"' : 'insuficientes')
                .'; categoría '.$categoria.'; prioridad '.$prioridad->label().' por impacto/urgencia y severidad del texto.',
            'causas' => $base['causas'],
            'pasos' => $base['pasos'],
            'consulta_rag' => trim($titulo.' '.$base['rag']),
            'terminos' => $terminos,
        ];
    }

    // ------------------------------------------------------------------ asistente

    /** Respuesta en Markdown a partir del contexto ITSM (ticket, SLA, RAG). */
    public function responder(array $datos): string
    {
        $pregunta = Texto::normalizar($datos['pregunta'] ?? '');
        $ticket = $datos['ticket'] ?? null;
        $fuentes = $datos['fuentes'] ?? [];
        $partes = [];

        if ($ticket) {
            $partes[] = "**Diagnóstico sugerido para {$ticket['codigo']}:**";
            $causas = $ticket['causas'] ?: (self::CONOCIMIENTO[$ticket['clasificacion'] ?? ''] ?? self::CONOCIMIENTO['general'])['causas'];
            $partes[] = 'El ticket "'.$ticket['titulo'].'" encaja con un **'.mb_strtolower($ticket['clasificacion'] ?? 'incidente').'**. '
                .'Las señales más probables son '.implode(' y ', array_slice($causas, 0, 2)).'.';
            $partes[] = '- Estado: '.$ticket['estado'].' · Prioridad: '.$ticket['prioridad'].' · SLA: '.$ticket['sla'];
            if (! empty($ticket['pasos'])) {
                $partes[] = "**Pasos recomendados:**\n".collect($ticket['pasos'])->values()->map(fn ($p, $i) => ($i + 1).'. '.$p)->join("\n");
            }
            if (! empty($ticket['confianza'])) {
                $partes[] = '**Confianza:** '.round($ticket['confianza'] * 100).'% · RAG: '.count($fuentes).' artículos consultados.';
            }
        } elseif (preg_match('/\b(sla|vence|vencer|venc|riesgo|incumpl)/', $pregunta)) {
            $partes[] = '**Riesgo SLA:**';
            $riesgo = $datos['sla_riesgo'] ?? [];
            if ($riesgo) {
                foreach ($riesgo as $t) {
                    $partes[] = "- **{$t['codigo']}** ({$t['prioridad']}) · {$t['sla']} · {$t['titulo']}";
                }
                $primero = $riesgo[0];
                $partes[] = "Prioridad inmediata para **{$primero['codigo']}**. Sugiero escalarlo y hacer seguimiento cada 10 minutos.";
            } else {
                $partes[] = 'No hay tickets abiertos con riesgo de incumplir el SLA en tu alcance.';
            }
        } elseif (preg_match('/\blog/', $pregunta)) {
            $partes[] = '**Análisis preliminar:**';
            $partes[] = 'Para un diagnóstico completo usa **Análisis de Logs**: detecta timeouts, uso del pool de conexiones por encima del 80 %, latencias mayores a 3 s y reintentos. '
                .'Si el patrón incluye "connection timeout" junto con un pool saturado, revisa los límites máximos, las conexiones no liberadas y la latencia de la base de datos.';
        } elseif (preg_match('/(solucion|resolver|como arreglo|timeout|error)/', $pregunta) && $fuentes) {
            $partes[] = '**Solución RAG recomendada:**';
            $partes[] = $fuentes[0]['fragmento'] ?? '';
        } else {
            $partes[] = 'He correlacionado tu consulta con el contexto ITSM disponible. Puedo profundizar en un ticket (indica su código, por ejemplo TKT-2026-00452), el SLA, un servicio, un activo o la base de conocimiento.';
        }

        if ($fuentes) {
            $partes[] = "**Artículos relacionados:**\n".collect($fuentes)->map(fn ($f) => "- {$f['codigo']} · {$f['titulo']} (relevancia ".round($f['relevancia'] * 100).'%)')->join("\n");
        }

        return implode("\n\n", array_filter($partes));
    }

    // ------------------------------------------------------------------ logs

    /**
     * Diagnóstico a partir de los patrones que detectó el analizador.
     *
     * @param  array<string, mixed>  $patrones
     * @return array{resumen: string, causas: list<string>, acciones: list<string>, confianza: float}
     */
    public function diagnosticarLogs(array $patrones): array
    {
        $causas = [];
        $acciones = [];
        $frases = [];

        if (! empty($patrones['pool']) && $patrones['pool'] >= 80) {
            $frases[] = 'saturación del pool de conexiones ('.$patrones['pool'].' %)';
            $causas[] = 'Pool de conexiones saturado';
            $acciones[] = 'Revisar conexiones no liberadas.';
        }
        if (! empty($patrones['timeout'])) {
            $frases[] = 'timeouts al conectar con la base de datos';
            $causas[] = 'Consultas lentas o base de datos sin capacidad';
            $acciones[] = 'Analizar query plan e índices.';
        }
        if (! empty($patrones['latencia'])) {
            $frases[] = 'tiempos de respuesta de '.$patrones['latencia'].' ms';
            $causas[] = 'Latencia elevada en la aplicación o la red';
            $acciones[] = 'Medir latencia de DB y red.';
        }
        if (! empty($patrones['cola'])) {
            $frases[] = 'cola de espera de '.$patrones['cola'].' solicitudes';
            $causas[] = 'Presión sostenida de concurrencia';
        }
        if (! empty($patrones['reintentos'])) {
            $frases[] = 'reintentos agotados';
            $causas[] = 'Reintentos que amplifican la carga';
            $acciones[] = 'Reintentar solo operaciones idempotentes con backoff.';
        }
        if (! empty($patrones['cache']) && $patrones['cache'] < 70) {
            $causas[] = 'Baja efectividad de caché ('.$patrones['cache'].' %)';
            $acciones[] = 'Revisar la política de caché de consultas frecuentes.';
        }
        if (! empty($patrones['memoria'])) {
            $frases[] = 'falta de memoria';
            $causas[] = 'Memoria insuficiente o fuga de memoria';
            $acciones[] = 'Revisar el consumo de memoria y reiniciar el servicio de forma controlada.';
        }
        if (! empty($patrones['pool'])) {
            $acciones[] = 'Ajustar el pool solo después de validar la capacidad.';
            $acciones[] = 'Crear una alerta preventiva al superar el 80 %.';
        }

        if (! $frases) {
            return [
                'resumen' => 'No se detectaron patrones críticos. Los eventos registrados parecen informativos; conviene ampliar la ventana de logs si el síntoma persiste.',
                'causas' => $causas ?: ['Sin patrones relevantes'],
                'acciones' => $acciones ?: ['Ampliar la ventana de tiempo de los logs.', 'Correlacionar con métricas de infraestructura.'],
                'confianza' => 0.6,
            ];
        }

        return [
            'resumen' => 'Existe alta probabilidad de '.implode(', ', $frases).'. La combinación sugiere presión sostenida durante la operación afectada (por ejemplo, la consulta de clientes).',
            'causas' => array_values(array_unique($causas)),
            'acciones' => array_values(array_unique($acciones)),
            'confianza' => round(min(0.95, 0.7 + 0.06 * count($frases)), 2),
        ];
    }
}
