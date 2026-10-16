<?php

namespace App\Services\AI;

use App\Enums\RolMensajeIa;
use App\Enums\TipoModeloIa;
use App\Models\ConversacionIa;
use App\Models\MensajeIa;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Services\AI\Nlp\MotorReglas;
use App\Services\SlaService;
use Illuminate\Support\Str;

/**
 * Asistente conversacional con contexto ITSM: ticket citado por código, tickets con SLA en riesgo
 * y fragmentos RAG. Persiste conversaciones_ia / mensajes_ia y registra tokens.
 */
class AsistenteChat
{
    public function __construct(
        private GestorIA $ia,
        private RecomendadorRag $rag,
        private SlaService $sla,
        private MotorReglas $motor,
    ) {}

    /**
     * @return array{texto: string, html: string, conversacion: ?ConversacionIa, fuentes: int}
     */
    public function responder(Usuario $usuario, string $pregunta, ?ConversacionIa $conversacion = null): array
    {
        $ticket = $this->ticketCitado($usuario, $pregunta);
        $organizacionId = $usuario->organizacion_id ?? $ticket?->organizacion_id;

        $contextoTicket = $ticket ? $this->contextoTicket($ticket) : null;
        $riesgo = $this->ticketsEnRiesgo($usuario);
        $fuentes = $this->rag->buscar(trim($pregunta.' '.($ticket?->titulo ?? '')), $organizacionId, $usuario, 3)
            ->map(fn ($f) => [
                'codigo' => $f['articulo']->codigo, 'titulo' => $f['articulo']->titulo,
                'relevancia' => $f['relevancia'], 'fragmento' => Str::limit($f['fragmento'], 600),
            ])->all();

        $solicitud = new SolicitudIA(
            tarea: SolicitudIA::RESPONDER,
            sistema: $this->promptSistema($contextoTicket, $riesgo, $fuentes),
            usuario: $pregunta,
            datos: ['pregunta' => $pregunta, 'ticket' => $contextoTicket, 'sla_riesgo' => $riesgo, 'fuentes' => $fuentes],
        );
        $respuesta = $this->ia->generar($solicitud);

        $conversacion = $this->guardar($usuario, $conversacion, $ticket, $pregunta, $respuesta, $solicitud);

        return [
            'texto' => $respuesta->texto,
            'html' => self::aHtml($respuesta->texto),
            'conversacion' => $conversacion,
            'fuentes' => count($fuentes),
        ];
    }

    /** Markdown → HTML escapando cualquier HTML del modelo (regla 03). */
    public static function aHtml(string $markdown): string
    {
        return Str::markdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }

    private function ticketCitado(Usuario $usuario, string $pregunta): ?Ticket
    {
        if (! preg_match_all('/TKT-\d{4}-\d{5}/i', $pregunta, $codigos)) {
            return null;
        }

        // visiblesPara + scope de organización: nunca se usa un ticket ajeno como contexto
        return Ticket::visiblesPara($usuario)->whereIn('codigo', array_map('strtoupper', $codigos[0]))
            ->with(['sla.regla', 'categoriaTicket'])->first();
    }

    private function contextoTicket(Ticket $ticket): array
    {
        $clasificacion = $ticket->clasificaciones()->latest('creado_en')->first();
        $recomendacion = $ticket->recomendaciones()->latest('creado_en')->first();
        $local = $clasificacion ? null : $this->motor->clasificar($ticket->titulo, $ticket->descripcion, $ticket->impacto->value, $ticket->urgencia->value);

        return [
            'codigo' => $ticket->codigo,
            'titulo' => $ticket->titulo,
            'descripcion' => Str::limit($ticket->descripcion, 800),
            'estado' => $ticket->estado->label(),
            'prioridad' => $ticket->prioridad->label(),
            'categoria' => $ticket->nombreCategoria(),
            'sla' => $this->sla->estadoSla($ticket)['texto'],
            'clasificacion' => $clasificacion ? strtok((string) $clasificacion->explicacion, ':') : $local['clasificacion'],
            'confianza' => $clasificacion?->confianza ?? $local['confianza'],
            'causas' => $recomendacion?->causas_probables ?? $local['causas'] ?? [],
            'pasos' => $recomendacion?->pasos_sugeridos ?? $local['pasos'] ?? [],
        ];
    }

    /** @return list<array{codigo: string, titulo: string, prioridad: string, sla: string}> */
    private function ticketsEnRiesgo(Usuario $usuario): array
    {
        return Ticket::visiblesPara($usuario)->abiertos()->whereNotNull('vencimiento_resolucion')
            ->with('sla.regla')->orderBy('vencimiento_resolucion')->limit(5)->get()
            ->map(fn (Ticket $t) => [
                'codigo' => $t->codigo, 'titulo' => Str::limit($t->titulo, 70),
                'prioridad' => $t->prioridad->label(), 'sla' => $this->sla->estadoSla($t)['texto'],
            ])->all();
    }

    /** Prompt para proveedores externos: solo datos técnicos del ticket y fragmentos (sin datos personales). */
    private function promptSistema(?array $ticket, array $riesgo, array $fuentes): string
    {
        $partes = ['Eres ServiceCloud AI, asistente de una mesa de ayuda (ITSM). Responde en español, en Markdown breve, con pasos concretos. '
            .'Usa solo el contexto dado; si falta información, dilo.'];
        if ($ticket) {
            $partes[] = "Ticket {$ticket['codigo']}: {$ticket['titulo']}. Descripción: {$ticket['descripcion']}. Estado: {$ticket['estado']}. "
                ."Prioridad: {$ticket['prioridad']}. Categoría: ".($ticket['categoria'] ?? '—').". SLA restante: {$ticket['sla']}. "
                .'Clasificación IA: '.$ticket['clasificacion'].'. Causas probables: '.implode('; ', $ticket['causas']).'.';
        }
        if ($riesgo) {
            $partes[] = 'Tickets con SLA más cercano: '.collect($riesgo)->map(fn ($t) => "{$t['codigo']} ({$t['prioridad']}, {$t['sla']})")->join(', ').'.';
        }
        foreach ($fuentes as $f) {
            $partes[] = "Fuente {$f['codigo']} «{$f['titulo']}»: {$f['fragmento']}";
        }

        return implode("\n\n", $partes);
    }

    private function guardar(Usuario $usuario, ?ConversacionIa $conversacion, ?Ticket $ticket, string $pregunta, RespuestaIA $respuesta, SolicitudIA $solicitud): ?ConversacionIa
    {
        // Las FK compuestas (organizacion_id, usuario_id) exigen que el usuario pertenezca a la organización:
        // el Administrador SaaS (sin organización) usa el asistente sin historial persistido.
        if ($usuario->organizacion_id === null) {
            return null;
        }

        $conversacion ??= ConversacionIa::create([
            'organizacion_id' => $usuario->organizacion_id,
            'usuario_id' => $usuario->id,
            'ticket_id' => $ticket?->id,
            'titulo' => Str::limit($pregunta, 80),
        ]);
        if ($ticket && ! $conversacion->ticket_id) {
            $conversacion->update(['ticket_id' => $ticket->id]);
        }

        MensajeIa::create(['conversacion_id' => $conversacion->id, 'rol' => RolMensajeIa::User, 'contenido' => $pregunta, 'tokens_entrada' => $respuesta->tokensEntrada]);
        MensajeIa::create([
            'conversacion_id' => $conversacion->id, 'rol' => RolMensajeIa::Assistant, 'contenido' => $respuesta->texto,
            'modelo_id' => $this->ia->modeloId(TipoModeloIa::Generativo, $respuesta), 'tokens_salida' => $respuesta->tokensSalida,
        ]);

        return $conversacion;
    }
}
