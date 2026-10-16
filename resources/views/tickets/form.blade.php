<x-modal-form
  :titulo="$ticket->exists ? 'Editar ticket' : 'Nuevo ticket'"
  :accion="$ticket->exists ? route('tickets.update', $ticket) : route('tickets.store')"
  :metodo="$ticket->exists ? 'PUT' : 'POST'"
  :archivos="! $ticket->exists">
  @if ($organizaciones)
    <x-form-field nombre="organizacion_id" etiqueta="Organización" :opciones="$organizaciones" vacio="Selecciona una organización" requerido completo />
  @endif
  <x-form-field nombre="titulo" etiqueta="Título" :valor="$ticket->titulo" requerido completo placeholder="Ej.: La aplicación demora demasiado y muestra error al consultar clientes" />
  <x-form-field nombre="descripcion" etiqueta="Descripción" tipo="textarea" :valor="$ticket->descripcion" requerido completo placeholder="Qué ocurre, desde cuándo, a quiénes afecta y qué mensaje aparece." />
  <x-form-field nombre="tipo" etiqueta="Tipo" :valor="$ticket->tipo" :opciones="App\Enums\TipoTicket::opciones()" requerido />
  <x-form-relacion nombre="categoria_id" etiqueta="Categoría" :registros="$categorias" :valor="$ticket->categoria_id" vacio="Sin categoría (la IA la sugerirá)" />
  <x-form-relacion nombre="servicio_id" etiqueta="Servicio" :registros="$servicios" :valor="$ticket->servicio_id" vacio="Sin servicio" />
  <x-form-relacion nombre="activo_id" etiqueta="Activo" :registros="$activos" :valor="$ticket->activo_id" :campo="fn ($a) => $a->codigo.' · '.$a->nombre" vacio="Sin activo" />
  <x-form-field nombre="impacto" etiqueta="Impacto" :valor="$ticket->impacto" :opciones="App\Enums\Impacto::opciones()" requerido />
  <x-form-field nombre="urgencia" etiqueta="Urgencia" :valor="$ticket->urgencia" :opciones="App\Enums\Urgencia::opciones()" requerido />
  @if ($puedePriorizar)
    <x-form-field nombre="prioridad" etiqueta="Prioridad" :valor="$ticket->exists ? $ticket->prioridad : null" :opciones="App\Enums\Prioridad::opciones()" vacio="Automática (impacto × urgencia)" />
  @else
    <x-meta-box etiqueta="Prioridad" valor="Se calcula por impacto × urgencia" />
  @endif
  @if ($puedeAsignar)
    <x-form-relacion nombre="tecnico_id" etiqueta="Técnico" :registros="$tecnicos" :valor="$ticket->tecnico_id" vacio="Sin asignar" />
  @endif
  @unless ($ticket->exists || $esSolicitante)
    <x-form-relacion nombre="solicitante_id" etiqueta="Solicitante" :registros="$solicitantes" vacio="Yo mismo" />
    <x-form-field nombre="origen" etiqueta="Canal" :opciones="App\Enums\OrigenTicket::opciones()" valor="portal" />
  @endunless
  @if ($analisisId ?? null)
    <input type="hidden" name="analisis_id" value="{{ $analisisId }}" />
    <p class="form-hint form-group full">Se vinculará al análisis de logs #{{ $analisisId }}.</p>
  @endif
  @unless ($ticket->exists)
    <x-form-field nombre="adjunto" etiqueta="Adjunto (opcional, máx. 10 MB)" tipo="file" completo />
  @endunless
</x-modal-form>
