<?php

namespace App\Services\AI;

use App\Enums\Prioridad;
use App\Enums\TipoModeloIa;
use App\Enums\TipoNotificacion;
use App\Models\CategoriaTicket;
use App\Models\ClasificacionIa;
use App\Models\ConfiguracionOrganizacion;
use App\Models\RecomendacionIa;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Services\AI\Nlp\MotorReglas;
use App\Services\NotificacionService;
use App\Services\TicketService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Clasificación y priorización NLP de tickets + recomendación RAG.
 * Guarda clasificaciones_ia y recomendaciones_ia (con sus fuentes y relevancia).
 */
class ClasificadorTickets
{
    public function __construct(
        private GestorIA $ia,
        private RecomendadorRag $rag,
        private MotorReglas $motor,
    ) {}

    /** Al registrar un ticket: clasifica si la organización lo tiene activo y aplica la prioridad si corresponde. */
    public function alCrearTicket(Ticket $ticket, Usuario $autor): ?ClasificacionIa
    {
        $config = ConfiguracionOrganizacion::sinFiltroOrganizacion()->find($ticket->organizacion_id);
        if ($config && ! $config->clasificacion_automatica) {
            return null;
        }
        try {
            return $this->analizar($ticket, $autor, aplicar: (bool) $config?->prioridad_automatica, conRag: $config?->rag_habilitado ?? true);
        } catch (Throwable $e) {
            Log::error('IA: no se pudo clasificar '.$ticket->codigo, ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function analizar(Ticket $ticket, ?Usuario $usuario = null, bool $aplicar = false, bool $conRag = true): ClasificacionIa
    {
        $solicitud = new SolicitudIA(
            tarea: SolicitudIA::CLASIFICAR,
            sistema: 'Eres un analista ITSM. Clasifica el ticket y responde SOLO un JSON con las claves: '
                .'clasificacion (texto corto, por ejemplo "Incidente de rendimiento"), categoria (una de: '.implode(', ', array_keys(MotorReglas::CATEGORIAS)).'), '
                .'prioridad (baja|media|alta|critica), confianza (0 a 1), explicacion (una o dos frases), causas (3 a 5 causas probables), '
                .'pasos (3 a 5 pasos de diagnóstico), consulta_rag (palabras clave para buscar en la base de conocimiento). Responde en español.',
            usuario: "Título: {$ticket->titulo}\nDescripción: {$ticket->descripcion}\nCategoría actual: ".($ticket->nombreCategoria() ?? 'sin categoría')
                ."\nImpacto: {$ticket->impacto->label()} · Urgencia: {$ticket->urgencia->label()}",
            datos: ['titulo' => $ticket->titulo, 'descripcion' => $ticket->descripcion, 'impacto' => $ticket->impacto->value, 'urgencia' => $ticket->urgencia->value],
            json: true,
        );
        $respuesta = $this->ia->generar($solicitud);
        $resultado = $this->validar($respuesta->json(), $ticket);

        return DB::transaction(function () use ($ticket, $usuario, $aplicar, $conRag, $respuesta, $resultado) {
            $clasificacion = ClasificacionIa::create([
                'ticket_id' => $ticket->id,
                'modelo_id' => $this->ia->modeloId(TipoModeloIa::Clasificacion, $respuesta),
                'categoria_sugerida' => $resultado['categoria'],
                'prioridad_sugerida' => $resultado['prioridad'],
                'confianza' => $resultado['confianza'],
                'explicacion' => $resultado['clasificacion'].': '.preg_replace('/^'.preg_quote($resultado['clasificacion'], '/').':\s*/u', '', $resultado['explicacion']),
                'aceptada' => null,
            ]);

            $recomendacion = RecomendacionIa::create([
                'ticket_id' => $ticket->id,
                'modelo_id' => $this->ia->modeloId(TipoModeloIa::Generativo, $respuesta),
                'diagnostico' => 'El síntoma encaja con un '.mb_strtolower($resultado['clasificacion']).'. Las señales más probables son '
                    .implode(' y ', array_slice($resultado['causas'], 0, 2)).'.',
                'causas_probables' => $resultado['causas'],
                'pasos_sugeridos' => $resultado['pasos'],
                'confianza' => $resultado['confianza'],
            ]);
            if ($conRag) {
                $fuentes = $this->rag->buscar($resultado['consulta_rag'].' '.$ticket->titulo.' '.$ticket->descripcion, $ticket->organizacion_id, null, 3);
                foreach ($fuentes as $fuente) {
                    $recomendacion->fuentes()->attach($fuente['articulo']->id, ['relevancia' => $fuente['relevancia']]);
                }
            }

            if ($aplicar && $usuario) {
                $this->aplicar($clasificacion, $ticket, $usuario);
            }

            return $clasificacion;
        });
    }

    /** Aceptar la sugerencia: aplica prioridad y categoría al ticket. */
    public function aceptar(ClasificacionIa $clasificacion, Usuario $usuario): void
    {
        $this->aplicar($clasificacion, $clasificacion->ticket, $usuario);
    }

    public function rechazar(ClasificacionIa $clasificacion): void
    {
        $clasificacion->update(['aceptada' => false]);
    }

    private function aplicar(ClasificacionIa $clasificacion, Ticket $ticket, Usuario $usuario): void
    {
        $tickets = app(TicketService::class);
        if ($clasificacion->prioridad_sugerida && $clasificacion->prioridad_sugerida !== $ticket->prioridad) {
            $tickets->cambiarPrioridad($ticket, $clasificacion->prioridad_sugerida, $usuario, 'sugerida por IA');
        }
        if (! $ticket->categoria_id && $clasificacion->categoria_sugerida) {
            $categoria = CategoriaTicket::sinFiltroOrganizacion()->where('organizacion_id', $ticket->organizacion_id)
                ->where('nombre', $clasificacion->categoria_sugerida)->first();
            if ($categoria) {
                $ticket->update(['categoria_id' => $categoria->id, 'categoria' => $categoria->nombre]);
                $tickets->historial($ticket, $usuario, 'categoria', null, $categoria->nombre.' (sugerida por IA)');
            }
        }
        $clasificacion->update(['aceptada' => true]);

        if ($ticket->tecnico_id) {
            app(NotificacionService::class)->notificar($ticket->tecnico_id, 'IA clasificó un ticket',
                "{$ticket->codigo}: ".mb_strtolower(strtok((string) $clasificacion->explicacion, ':')).', prioridad '.$clasificacion->prioridad_sugerida?->label().'.', TipoNotificacion::Ia, $ticket);
        }
    }

    /** Valida la respuesta del modelo; lo que falte o no sea válido se completa con el motor local. */
    private function validar(?array $datos, Ticket $ticket): array
    {
        $local = $this->motor->clasificar($ticket->titulo, $ticket->descripcion, $ticket->impacto->value, $ticket->urgencia->value);
        if (! $datos) {
            return $local;
        }
        $lista = fn ($v) => is_array($v) ? array_values(array_filter(array_map(fn ($x) => is_string($x) ? trim($x) : null, $v))) : [];

        return [
            'clasificacion' => is_string($datos['clasificacion'] ?? null) && $datos['clasificacion'] !== '' ? mb_substr($datos['clasificacion'], 0, 80) : $local['clasificacion'],
            'categoria' => in_array($datos['categoria'] ?? null, array_keys(MotorReglas::CATEGORIAS), true) ? $datos['categoria'] : $local['categoria'],
            'prioridad' => Prioridad::tryFrom((string) ($datos['prioridad'] ?? ''))?->value ?? $local['prioridad'],
            'confianza' => is_numeric($datos['confianza'] ?? null) ? round(max(0, min(1, (float) $datos['confianza'])), 2) : $local['confianza'],
            'explicacion' => is_string($datos['explicacion'] ?? null) ? mb_substr($datos['explicacion'], 0, 1000) : $local['explicacion'],
            'causas' => array_slice($lista($datos['causas'] ?? null) ?: $local['causas'], 0, 6),
            'pasos' => array_slice($lista($datos['pasos'] ?? null) ?: $local['pasos'], 0, 6),
            'consulta_rag' => is_string($datos['consulta_rag'] ?? null) ? $datos['consulta_rag'] : $local['consulta_rag'],
        ];
    }
}
