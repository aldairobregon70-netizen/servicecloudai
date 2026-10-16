<?php

namespace App\Http\Controllers;

use App\Enums\EstadoTicket;
use App\Enums\Impacto;
use App\Enums\Rol;
use App\Enums\TipoTicket;
use App\Enums\Urgencia;
use App\Http\Requests\CambiarEstadoTicketRequest;
use App\Http\Requests\ComentarTicketRequest;
use App\Http\Requests\GuardarTicketRequest;
use App\Models\Activo;
use App\Models\AnalisisLogIa;
use App\Models\CategoriaTicket;
use App\Models\ClasificacionIa;
use App\Models\Organizacion;
use App\Models\Servicio;
use App\Models\Ticket;
use App\Models\TicketAdjunto;
use App\Models\Usuario;
use App\Services\ActividadTicket;
use App\Services\AI\ClasificadorTickets;
use App\Services\SlaService;
use App\Services\TicketService;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends Controller
{
    public function __construct(private TicketService $tickets, private SlaService $sla) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Ticket::class);

        return view('tickets.index', [
            'tickets' => $this->consulta($request)->paginate(config('servicecloud.paginacion'))->withQueryString(),
            'sla' => $this->sla,
        ]);
    }

    public function exportar(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Ticket::class);
        $filas = $this->consulta($request)->with(['servicio:id,nombre', 'solicitante:id,nombre'])->get()->map(fn (Ticket $t) => [
            $t->codigo, $t->titulo, $t->organizacion?->razon_social, $t->prioridad, $t->estado, $t->tecnico?->nombre ?? 'Sin asignar',
            $this->sla->estadoSla($t)['texto'], $t->creado_en, $t->tipo, $t->nombreCategoria(), $t->servicio?->nombre, $t->solicitante?->nombre,
            $t->vencimiento_resolucion, $t->resuelto_en,
        ]);

        return Csv::descargar('tickets', ['Código', 'Título', 'Organización', 'Prioridad', 'Estado', 'Técnico', 'SLA', 'Fecha', 'Tipo', 'Categoría', 'Servicio', 'Solicitante', 'Vence', 'Resuelto'], $filas);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Ticket::class);
        // Prellenado opcional (p. ej. "Crear ticket desde análisis" del AI Log Analyzer)
        $ticket = new Ticket([
            'tipo' => TipoTicket::Incidente,
            'impacto' => Impacto::tryFrom((string) $request->query('impacto')) ?? Impacto::Medio,
            'urgencia' => Urgencia::tryFrom((string) $request->query('urgencia')) ?? Urgencia::Media,
            'titulo' => $request->query('titulo'), 'descripcion' => $request->query('descripcion'),
        ]);
        if ($request->filled('categoria')) {
            $ticket->categoria_id = CategoriaTicket::where('nombre', (string) $request->query('categoria'))->value('id');
        }

        return $this->vistaModal('tickets.form', $this->datosFormulario($request, $ticket) + [
            'analisisId' => AnalisisLogIa::whereKey((int) $request->query('analisis'))->value('id'),
        ]);
    }

    public function store(GuardarTicketRequest $request): JsonResponse|RedirectResponse
    {
        $datos = $request->datosConOrganizacion();
        $analisisId = $datos['analisis_id'] ?? null;
        unset($datos['adjunto'], $datos['analisis_id']);
        $ticket = $this->tickets->crear($datos, $request->user());
        if ($request->hasFile('adjunto')) {
            $this->tickets->adjuntar($ticket, $request->file('adjunto'), $request->user());
        }
        if ($analisisId && $analisis = AnalisisLogIa::where('organizacion_id', $ticket->organizacion_id)->find($analisisId)) {
            $analisis->update(['ticket_id' => $ticket->id]);
            $analisis->logs()->update(['ticket_id' => $ticket->id]);
        }

        return $this->guardado(route('tickets.show', $ticket), ['Ticket creado', "{$ticket->codigo} · prioridad {$ticket->prioridad->label()}, vence {$ticket->vencimiento_resolucion?->format('d/m/Y H:i')}."]);
    }

    public function show(Request $request, Ticket $ticket): View
    {
        $this->authorize('view', $ticket);
        $ticket->load(['organizacion', 'solicitante', 'tecnico', 'servicio', 'activo', 'categoriaTicket', 'sla.regla', 'politicaSla', 'adjuntos', 'incidencia', 'problemas']);
        $usuario = $request->user();

        return view('tickets.show', [
            'ticket' => $ticket,
            'estadoSla' => $this->sla->estadoSla($ticket),
            'actividad' => app(ActividadTicket::class)->para($ticket, $usuario),
            'clasificacion' => $ticket->clasificaciones()->latest('creado_en')->latest('id')->first(),
            // Las fuentes internas no se muestran a quien no puede verlas (solicitante)
            'recomendacion' => $ticket->recomendaciones()->latest('creado_en')->latest('id')
                ->with(['fuentes' => fn ($q) => $q->when(! $usuario->can('verInternos', $ticket), fn ($w) => $w->where('visibilidad', '!=', 'interna'))])
                ->first(),
            'estadosPosibles' => collect([$ticket->estado, ...$ticket->estado->transiciones()])
                ->filter(fn (EstadoTicket $e) => $e === $ticket->estado || $ticket->tecnico_id || ! in_array($e, [EstadoTicket::Asignado, EstadoTicket::EnProgreso], true))
                ->mapWithKeys(fn (EstadoTicket $e) => [$e->value => $e->label()])->all(),
        ]);
    }

    public function edit(Request $request, Ticket $ticket): View
    {
        $this->authorize('update', $ticket);

        return $this->vistaModal('tickets.form', $this->datosFormulario($request, $ticket));
    }

    public function update(GuardarTicketRequest $request, Ticket $ticket): JsonResponse|RedirectResponse
    {
        $this->tickets->actualizar($ticket, $request->datosConOrganizacion(), $request->user());

        return $this->guardado(route('tickets.show', $ticket), ['Ticket actualizado', "{$ticket->codigo}: cambios guardados correctamente."]);
    }

    public function cambiarEstado(CambiarEstadoTicketRequest $request, Ticket $ticket): JsonResponse|RedirectResponse
    {
        $nuevo = EstadoTicket::from($request->string('estado'));
        $this->tickets->cambiarEstado($ticket, $nuevo, $request->user(), $request->input('solucion'));

        return $this->guardado(route('tickets.show', $ticket), ['Estado actualizado', "{$ticket->codigo} ahora está en \"{$nuevo->label()}\"."]);
    }

    public function comentar(ComentarTicketRequest $request, Ticket $ticket): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($request, $ticket) {
            if (filled($request->input('contenido'))) {
                $this->tickets->comentar($ticket, $request->user(), $request->string('contenido'), $request->esInterno());
            }
            if ($request->hasFile('adjunto')) {
                $this->tickets->adjuntar($ticket, $request->file('adjunto'), $request->user());
            }
        });

        return $this->guardado(route('tickets.show', $ticket), ['Nota agregada', 'Se añadió '.($request->esInterno() ? 'una nota interna' : 'un comentario')." a {$ticket->codigo}."]);
    }

    /** POST /tickets/{ticket}/clasificar: analiza (o reanaliza) el ticket con IA. */
    public function clasificar(Request $request, Ticket $ticket, ClasificadorTickets $clasificador): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $ticket);
        $clasificacion = $clasificador->analizar($ticket, $request->user());

        return $this->guardado(route('tickets.show', $ticket), ['Análisis IA completado', strtok((string) $clasificacion->explicacion, ':').' · confianza '.round($clasificacion->confianza * 100).'%.']);
    }

    /** PATCH /tickets/{ticket}/clasificacion/{clasificacion}/{decision}: aceptar o rechazar la sugerencia. */
    public function decidirClasificacion(Request $request, Ticket $ticket, ClasificacionIa $clasificacion, string $decision, ClasificadorTickets $clasificador): RedirectResponse
    {
        $this->authorize('priorizar', $ticket);
        abort_unless($clasificacion->ticket_id === $ticket->id, 404);

        if ($decision === 'aceptar') {
            $clasificador->aceptar($clasificacion, $request->user());

            return back()->with('ok', ['Sugerencia aplicada', 'Se aplicó la prioridad y categoría sugeridas por la IA.']);
        }
        $clasificador->rechazar($clasificacion);

        return back()->with('aviso', ['Sugerencia rechazada', 'La clasificación IA quedó registrada como rechazada.']);
    }

    /** "Aplicar recomendación": agrega los pasos sugeridos por la IA como nota interna del plan de resolución. */
    public function aplicarRecomendacion(Request $request, Ticket $ticket): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $ticket);
        $recomendacion = $ticket->recomendaciones()->latest('creado_en')->firstOrFail();
        $pasos = collect($recomendacion->pasos_sugeridos ?? [])->values()->map(fn ($paso, $i) => ($i + 1).'. '.$paso)->join("\n");
        abort_if($pasos === '', 422, 'La recomendación no tiene pasos sugeridos.');

        $this->tickets->comentar($ticket, $request->user(), "Plan de resolución sugerido por ServiceCloud AI:\n".$pasos, true);

        return $this->guardado(route('tickets.show', $ticket), ['Recomendación aplicada', 'Se añadieron los pasos sugeridos por IA al plan de resolución.']);
    }

    public function descargarAdjunto(Ticket $ticket, TicketAdjunto $adjunto): StreamedResponse
    {
        $this->authorize('view', $ticket);
        abort_unless($adjunto->ticket_id === $ticket->id && Storage::disk('local')->exists($adjunto->ruta_storage), 404);

        return Storage::disk('local')->download($adjunto->ruta_storage, $adjunto->nombre_original);
    }

    private function consulta(Request $request): Builder
    {
        return Ticket::query()
            ->visiblesPara($request->user())
            ->with(['organizacion:id,razon_social', 'tecnico:id,nombre', 'sla.regla'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $texto = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('codigo', 'ilike', $texto)->orWhere('titulo', 'ilike', $texto)
                    ->orWhereHas('organizacion', fn ($o) => $o->where('razon_social', 'ilike', $texto))
                    ->orWhereHas('tecnico', fn ($o) => $o->where('nombre', 'ilike', $texto)));
            })
            ->when($request->filled('prioridad'), fn ($q) => $q->where('prioridad', $request->string('prioridad')))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))
            ->orderByDesc('creado_en');
    }

    private function datosFormulario(Request $request, Ticket $ticket): array
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();
        $esSolicitante = ! $usuario->can('asignar', Ticket::class) && ! $usuario->tieneRol(Rol::Tecnico);

        return [
            'ticket' => $ticket,
            'esSolicitante' => $esSolicitante,
            'puedeAsignar' => $usuario->can('asignar', Ticket::class),
            'puedePriorizar' => $ticket->exists ? $usuario->can('priorizar', $ticket) : $usuario->can('asignar', Ticket::class),
            'organizaciones' => $usuario->esAdminSaas() && ! $ticket->exists ? Organizacion::where('estado', '!=', 'inactiva')->orderBy('razon_social')->pluck('razon_social', 'id')->all() : [],
            'categorias' => $this->opcionesRelacion(CategoriaTicket::query()->orderBy('nombre'), $ticket),
            'servicios' => $this->opcionesRelacion(Servicio::query()->where('estado', '!=', 'retirado')->orderBy('nombre'), $ticket),
            'activos' => $this->opcionesRelacion(Activo::query()->where('estado', '!=', 'retirado')->orderBy('nombre'), $ticket),
            'solicitantes' => $esSolicitante || $ticket->exists ? collect() : $this->opcionesRelacion(Usuario::query()->whereNotNull('organizacion_id')->where('estado', 'activo')->orderBy('nombre'), $ticket),
            'tecnicos' => $this->opcionesRelacion(
                Usuario::query()->where('estado', 'activo')->whereHas('rolesOrganizacion', fn ($q) => $q->where('codigo', Rol::Tecnico->value))->orderBy('nombre'),
                $ticket,
            ),
        ];
    }
}
