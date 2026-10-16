@extends('layouts.app')

@section('content')
  <x-page-header :titulo="config('servicecloud.menu.dashboard.titulo')" :descripcion="config('servicecloud.menu.dashboard.descripcion')">
    @can('modulo', 'reportes')
      <a class="btn btn-secondary" href="{{ route('reportes.index') }}"><i data-lucide="bar-chart-3"></i> Ver reportes</a>
    @endcan
    <a class="btn btn-primary" href="{{ route('tickets.index') }}"><i data-lucide="plus"></i> Nuevo ticket</a>
  </x-page-header>

  <div class="grid kpi-grid">
    @foreach ($kpis as [$icono, $valor, $etiqueta, $tendencia, $clase])
      <x-kpi :icono="$icono" :valor="$valor" :etiqueta="$etiqueta" :tendencia="$tendencia" :clase="$clase" />
    @endforeach
  </div>

  <div class="grid chart-grid">
    <x-chart-card titulo="Tickets por estado" subtitulo="Distribución actual" id="chartStatus" :grafico="$graficos['estado']" />
    <x-chart-card titulo="Tickets por prioridad" subtitulo="Volumen por criticidad" id="chartPriority" :grafico="$graficos['prioridad']" />
    <x-chart-card titulo="Evolución últimos 7 días" subtitulo="Tickets creados vs resueltos" id="chartTrend" :grafico="$graficos['tendencia']" />
    <x-chart-card titulo="Cumplimiento SLA" subtitulo="Objetivo mensual 95%" id="chartSLA" :grafico="$graficos['sla']" />
  </div>

  <div class="grid dashboard-bottom">
    <div class="card insights">
      <div class="insights-head"><div class="spark"><i data-lucide="sparkles"></i></div><div><h3>ServiceCloud AI Insights</h3><p>Señales generadas por NLP, RAG y análisis técnico</p></div></div>
      <div class="insight-list">
        @foreach ($insights as [$valor, $texto])
          <div class="insight-item"><b>{{ $valor }}</b><span>{{ $texto }}</span></div>
        @endforeach
      </div>
    </div>
    <div class="card activity">
      <div class="card-title"><h3>Actividad reciente</h3><span>Tiempo real</span></div>
      @forelse ($actividad as [$titulo, $subtitulo])
        <x-activity :titulo="$titulo" :subtitulo="$subtitulo" />
      @empty
        <x-empty-state titulo="Sin actividad" texto="Aún no hay movimientos registrados." icono="activity" />
      @endforelse
    </div>
  </div>
@endsection
