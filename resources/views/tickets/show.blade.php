@extends('layouts.app')

@section('titulo', $ticket->codigo)

@php
    use App\Support\Formato;
    $usuario = auth()->user();
    $colorPrioridad = ['critica' => '#ff9aab', 'alta' => '#ffd468', 'media' => '#8ec5ff', 'baja' => '#b7c9ef'];
    $iconoCategoria = ['Redes' => 'network', 'Aplicaciones' => 'app-window', 'Bases de datos' => 'database', 'Seguridad' => 'shield-check', 'Cloud' => 'cloud', 'Hardware' => 'cpu'];
@endphp

@section('content')
  <x-page-header titulo="Detalle de ticket" :descripcion="$ticket->codigo.' · Gestión del incidente y asistencia inteligente.'">
    <a class="btn btn-secondary" id="backTickets" href="{{ route('tickets.index') }}"><i data-lucide="arrow-left"></i> Volver</a>
    @can('update', $ticket)
      <button class="btn btn-primary" type="button" id="ticketEdit" data-modal-url="{{ route('tickets.edit', $ticket) }}" data-modal-titulo="Editar ticket"><i data-lucide="pencil"></i> Editar</button>
    @endcan
  </x-page-header>

  <div class="grid ticket-detail-layout">
    <section class="card ticket-main">
      <div class="card-title">
        <div><span>Ticket {{ $ticket->codigo }} · {{ $ticket->solicitante?->nombre }}</span><h3 class="ticket-title">{{ $ticket->titulo }}</h3></div>
        <x-badge :valor="$ticket->prioridad" />
      </div>
      <div class="ticket-meta">
        <x-badge :valor="$ticket->estado" />
        <x-badge :valor="$ticket->prioridad" />
        <span class="badge badge-cyan">{{ $ticket->nombreCategoria() ?? 'Sin categoría' }}</span>
        <x-badge :valor="$ticket->tipo" />
        @if ($ticket->incidencia)<span class="badge badge-purple">{{ $ticket->incidencia->codigo }}</span>@endif
        @foreach ($ticket->problemas as $problema)<span class="badge badge-yellow">{{ $problema->codigo }}</span>@endforeach
      </div>
      <p class="ticket-desc" style="white-space:pre-line">{{ $ticket->descripcion }}</p>

      <div class="meta-grid">
        <x-meta-box etiqueta="Organización" :valor="$ticket->organizacion?->razon_social" />
        <x-meta-box etiqueta="Servicio" :valor="$ticket->servicio?->nombre" />
        <x-meta-box etiqueta="Técnico" :valor="$ticket->tecnico?->nombre ?? 'Sin asignar'" />
        <x-meta-box etiqueta="SLA restante">
          @if ($estadoSla['tipo'] === 'cumplido')<x-badge valor="Cumplido" color="green" />
          @elseif ($estadoSla['tipo'] === 'incumplido')<x-badge valor="Incumplido" color="red" />
          @else<em style="font-style:normal;color:{{ in_array($estadoSla['tipo'], ['vencido', 'riesgo'], true) ? '#ff9aab' : 'inherit' }}">{{ $estadoSla['texto'] }}{{ $estadoSla['tipo'] === 'pausa' ? ' · en pausa' : '' }}</em>@endif
        </x-meta-box>
        <x-meta-box etiqueta="Creado" :valor="Formato::fechaHora($ticket->creado_en)" />
        <x-meta-box etiqueta="Canal" :valor="$ticket->origen->label()" />
      </div>

      @if ($ticket->solucion)
        <div class="diagnosis" style="margin-top:0"><h4>Solución</h4><p style="white-space:pre-line">{{ $ticket->solucion }}</p></div>
      @endif

      @if ($ticket->adjuntos->isNotEmpty())
        <div class="card-title" style="margin-top:16px"><h3>Adjuntos</h3><span>{{ $ticket->adjuntos->count() }}</span></div>
        <div class="rag-articles">
          @foreach ($ticket->adjuntos as $adjunto)
            <x-rag-article icono="paperclip" :titulo="$adjunto->nombre_original" :subtitulo="number_format(($adjunto->tamano_bytes ?? 0) / 1024, 0).' KB · '.Formato::fechaHora($adjunto->creado_en)" :href="route('tickets.adjuntos.descargar', [$ticket, $adjunto])" />
          @endforeach
        </div>
      @endif

      @if ($usuario->can('update', $ticket) || $usuario->can('comentar', $ticket))
        <form class="toolbar" style="margin:16px 0 0" method="POST" action="{{ route('tickets.estado', $ticket) }}" data-ajax id="formEstado">
          @csrf @method('PATCH')
          @can('update', $ticket)
            <select class="field" id="detailStatus" name="estado">
              @foreach ($estadosPosibles as $valor => $etiqueta)
                <option value="{{ $valor }}" @selected($valor === $ticket->estado->value)>{{ $etiqueta }}</option>
              @endforeach
            </select>
            <button class="btn btn-secondary" type="submit" id="saveStatus"><i data-lucide="refresh-cw"></i> Cambiar estado</button>
          @endcan
          <button class="btn btn-secondary" type="button" id="addNote" data-modal-template="#tplNota"><i data-lucide="message-square-plus"></i> Agregar nota</button>
          @can('update', $ticket)
            <div class="form-group grow" data-campo="solucion" id="grupoSolucion" hidden style="flex-basis:100%">
              <label for="f_solucion">Solución aplicada</label>
              <textarea class="form-control" id="f_solucion" name="solucion" placeholder="Describe qué se hizo para resolverlo...">{{ $ticket->solucion }}</textarea>
              <span class="form-error" hidden></span>
            </div>
          @endcan
        </form>
      @endif

      <div class="timeline">
        <div class="card-title"><h3>Actividad del ticket</h3><span>Historial</span></div>
        @forelse ($actividad as $evento)
          <x-timeline-item :icono="$evento['icono']" :titulo="$evento['titulo']" :texto="$evento['texto']" />
        @empty
          <x-empty-state titulo="Sin actividad" icono="history" />
        @endforelse
      </div>
    </section>

    <aside class="card ai-panel ticket-ai">
      <div class="ai-label"><i data-lucide="brain-circuit"></i> AI Classification</div>
      @if ($clasificacion)
        <div class="ai-class"><span>Clasificación</span><b>{{ \Illuminate\Support\Str::contains((string) $clasificacion->explicacion, ':') ? \Illuminate\Support\Str::before($clasificacion->explicacion, ':') : $ticket->tipo->label() }}</b></div>
        <div class="ai-class"><span>Categoría</span><b>{{ $clasificacion->categoria_sugerida ?? '—' }}</b></div>
        <div class="ai-class"><span>Prioridad</span><b style="color:{{ $colorPrioridad[$clasificacion->prioridad_sugerida?->value] ?? '#fff' }}">{{ $clasificacion->prioridad_sugerida?->label() ?? '—' }}</b></div>
        <div class="ai-class"><span>Confianza</span><div class="confidence"><div class="progress"><span style="width:{{ round($clasificacion->confianza * 100) }}%"></span></div><b>{{ round($clasificacion->confianza * 100) }}%</b></div></div>
        @if ($clasificacion->aceptada === null && $usuario->can('priorizar', $ticket))
          <div class="page-actions" style="margin-bottom:10px">
            <button class="btn btn-secondary btn-sm" type="button" style="flex:1" data-confirmar="¿Aplicar la prioridad {{ $clasificacion->prioridad_sugerida?->label() }} y la categoría {{ $clasificacion->categoria_sugerida }} sugeridas por la IA?" data-accion="{{ route('tickets.clasificacion.decidir', [$ticket, $clasificacion, 'aceptar']) }}" data-metodo="PATCH" data-titulo-confirmar="Aceptar sugerencia IA" data-boton-confirmar="Aceptar" data-icono-confirmar="check" data-clase-confirmar="btn-primary"><i data-lucide="check"></i> Aceptar</button>
            <button class="btn btn-secondary btn-sm" type="button" style="flex:1" data-confirmar="¿Rechazar la clasificación sugerida por la IA?" data-accion="{{ route('tickets.clasificacion.decidir', [$ticket, $clasificacion, 'rechazar']) }}" data-metodo="PATCH" data-titulo-confirmar="Rechazar sugerencia IA" data-boton-confirmar="Rechazar" data-icono-confirmar="x"><i data-lucide="x"></i> Rechazar</button>
          </div>
        @elseif ($clasificacion->aceptada !== null)
          <p class="form-hint" style="margin:0 0 10px">Sugerencia {{ $clasificacion->aceptada ? 'aceptada' : 'rechazada' }}.</p>
        @endif
      @else
        <div class="ai-class"><span>Estado</span><b>Pendiente de análisis</b></div>
        <p class="ticket-desc">La IA todavía no clasificó este ticket.</p>
      @endif
      @can('update', $ticket)
        <form method="POST" action="{{ route('tickets.clasificar', $ticket) }}" data-ajax id="formClasificar" style="margin-bottom:6px">
          @csrf
          <button class="btn btn-secondary btn-sm" style="width:100%" type="submit"><i data-lucide="sparkles"></i> {{ $clasificacion ? 'Reanalizar con IA' : 'Analizar con IA' }}</button>
        </form>
      @endcan

      <div class="card-title" style="margin-top:17px"><h3>Posibles causas</h3><span>NLP + contexto</span></div>
      @if ($recomendacion && $recomendacion->causas_probables)
        <ul class="ai-list">
          @foreach ($recomendacion->causas_probables as $causa)<li>{{ $causa }}</li>@endforeach
        </ul>
      @else
        <p class="ticket-desc">Sin causas sugeridas todavía.</p>
      @endif

      <div class="card-title"><h3>Knowledge Suggestion</h3><span>RAG activo</span></div>
      <div class="rag-articles">
        @forelse ($recomendacion?->fuentes ?? [] as $articulo)
          <x-rag-article :icono="$iconoCategoria[$articulo->categoria] ?? 'book-open'" :titulo="$articulo->titulo" :subtitulo="'Relevancia '.round($articulo->pivot->relevancia * 100).'%'"
            :href="Route::has('conocimiento.show') ? route('conocimiento.show', $articulo) : null" />
        @empty
          <p class="ticket-desc" style="margin:0">Sin artículos sugeridos todavía.</p>
        @endforelse
      </div>

      @can('update', $ticket)
        @if ($recomendacion && $recomendacion->pasos_sugeridos)
          <form method="POST" action="{{ route('tickets.recomendacion', $ticket) }}" data-ajax id="formRecomendacion">
            @csrf
            <button class="btn btn-primary" style="width:100%;margin-bottom:8px" id="applyAI" type="submit"><i data-lucide="wand-sparkles"></i> Aplicar recomendación</button>
          </form>
        @endif
      @endcan
      <a class="btn btn-secondary" style="width:100%" id="askAI" href="{{ route('asistente.index', ['prompt' => 'Analiza el ticket '.$ticket->codigo.' y dame un diagnóstico técnico priorizado.']) }}"><i data-lucide="bot"></i> Preguntar a la IA</a>
    </aside>
  </div>

  @push('templates')
    <template id="tplNota" data-titulo="Agregar nota al ticket">
      <div data-modal-body>
        <form id="formNota" class="form-grid" method="POST" action="{{ route('tickets.comentarios', $ticket) }}" data-ajax enctype="multipart/form-data" novalidate>
          @csrf
          <div class="form-group full" data-campo="contenido">
            <label for="ticketNote">{{ $usuario->can('verInternos', $ticket) ? 'Nota' : 'Comentario' }}</label>
            <textarea class="form-control" id="ticketNote" name="contenido" placeholder="Describe el avance, evidencia o siguiente acción..."></textarea>
            <span class="form-error" hidden></span>
          </div>
          <div class="form-group full" data-campo="adjunto">
            <label for="f_adjunto_nota">Adjunto (opcional, máx. 10 MB)</label>
            <input class="form-control" id="f_adjunto_nota" type="file" name="adjunto" />
            <span class="form-error" hidden></span>
          </div>
          @can('verInternos', $ticket)
            <label class="form-group full" style="display:flex;gap:8px;align-items:center;font-size:11px;color:#c2d3f7">
              <input type="checkbox" name="es_interno" value="1" checked /> Nota interna (no visible para el solicitante)
            </label>
          @endcan
        </form>
      </div>
      <div data-modal-footer>
        <button class="btn btn-secondary" type="button" data-cerrar-modal>Cancelar</button>
        <button class="btn btn-primary" type="submit" form="formNota"><i data-lucide="message-square-plus"></i> Agregar nota</button>
      </div>
    </template>
  @endpush

  @push('scripts')
    <script>
      (() => {
        const select = document.getElementById('detailStatus');
        const grupo = document.getElementById('grupoSolucion');
        if (!select || !grupo) return;
        const actualizar = () => { grupo.hidden = select.value !== 'resuelto'; };
        select.addEventListener('change', actualizar);
        actualizar();
      })();
    </script>
  @endpush
@endsection
