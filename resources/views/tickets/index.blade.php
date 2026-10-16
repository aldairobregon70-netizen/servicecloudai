@extends('layouts.app')

@section('content')
  <x-page-header :titulo="config('servicecloud.menu.tickets.titulo')" :descripcion="config('servicecloud.menu.tickets.descripcion')">
    @can('create', App\Models\Ticket::class)
      <button class="btn btn-primary" type="button" id="newTicket" data-modal-url="{{ route('tickets.create') }}" data-modal-titulo="Nuevo ticket"><i data-lucide="plus"></i> Nuevo ticket</button>
    @endcan
  </x-page-header>

  <x-toolbar-filtros placeholder="Buscar por código, título, organización..." :estados="App\Enums\EstadoTicket::opciones()" :exportar="route('tickets.exportar', request()->query())">
    <select class="field" name="prioridad">
      <option value="">Todas las prioridades</option>
      @foreach (array_reverse(App\Enums\Prioridad::opciones(), true) as $valor => $etiqueta)
        <option value="{{ $valor }}" @selected(request('prioridad') === $valor)>{{ $etiqueta }}</option>
      @endforeach
    </select>
  </x-toolbar-filtros>

  <div class="card table-card"><div class="table-scroll"><table id="ticketsTable">
    <thead><tr><th>Código</th><th>Título</th><th>Organización</th><th>Prioridad</th><th>Estado</th><th>Técnico</th><th>SLA</th><th>Fecha</th></tr></thead>
    <tbody>
      @forelse ($tickets as $ticket)
        @php($estadoSla = $sla->estadoSla($ticket))
        <tr data-href="{{ route('tickets.show', $ticket) }}" style="cursor:pointer">
          <td><a class="table-link" href="{{ route('tickets.show', $ticket) }}">{{ $ticket->codigo }}</a></td>
          <td style="max-width:340px">{{ $ticket->titulo }}</td>
          <td>{{ $ticket->organizacion?->razon_social }}</td>
          <td><x-badge :valor="$ticket->prioridad" /></td>
          <td><x-badge :valor="$ticket->estado" /></td>
          <td>{{ $ticket->tecnico?->nombre ?? 'Sin asignar' }}</td>
          <td>
            @switch($estadoSla['tipo'])
              @case('cumplido') <x-badge valor="Cumplido" color="green" /> @break
              @case('incumplido') <x-badge valor="Incumplido" color="red" /> @break
              @case('sin') — @break
              @default
                <span style="font-weight:800;color:{{ in_array($estadoSla['tipo'], ['vencido', 'riesgo'], true) ? '#ff9aab' : '#a9c8ff' }}" @if ($estadoSla['tipo'] === 'pausa') title="SLA en pausa (en espera)" @endif>{{ $estadoSla['texto'] }}</span>
            @endswitch
          </td>
          <td>{{ App\Support\Formato::fechaHora($ticket->creado_en) }}</td>
        </tr>
      @empty
        <tr><td colspan="8"><x-empty-state titulo="Sin tickets" texto="No hay tickets que coincidan con los filtros." /></td></tr>
      @endforelse
    </tbody>
  </table></div>
  {{ $tickets->links() }}
  </div>
@endsection
