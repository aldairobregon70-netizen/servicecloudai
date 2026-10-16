@extends('layouts.app')

@section('content')
  <x-page-header :titulo="config('servicecloud.menu.logs.titulo')" :descripcion="config('servicecloud.menu.logs.descripcion')" />

  <div class="grid log-layout">
    <section class="card log-editor">
      <div class="card-title"><h3>Logs de entrada</h3><span>Texto plano</span></div>
      @if ($organizaciones)
        <select class="field" id="logOrganizacion" style="width:100%;margin-bottom:10px" aria-label="Organización">
          <option value="">Analizar sin guardar (sin organización)</option>
          @foreach ($organizaciones as $id => $nombre)<option value="{{ $id }}">Guardar en {{ $nombre }}</option>@endforeach
        </select>
      @endif
      <textarea id="logInput" spellcheck="false" data-url="{{ route('logs.analizar') }}">{{ $ejemplo }}</textarea>
      <div class="page-actions" style="margin-top:12px">
        <button class="btn btn-secondary" id="clearLogs" type="button"><i data-lucide="eraser"></i> Limpiar</button>
        <button class="btn btn-primary" id="analyzeLogs" type="button"><i data-lucide="sparkles"></i> Analizar Logs</button>
      </div>
      @if ($recientes->isNotEmpty())
        <div class="card-title" style="margin-top:18px"><h3>Análisis recientes</h3><span>{{ $recientes->count() }}</span></div>
        @foreach ($recientes as $analisis)
          <div class="activity-row"><div class="activity-dot"></div><div><b>{{ $analisis->severidad?->label() }} · {{ App\Support\Formato::fechaHora($analisis->creado_en) }}</b><span>{{ \Illuminate\Support\Str::limit($analisis->resumen, 110) }}</span></div></div>
        @endforeach
      @endif
    </section>
    <aside class="card log-results" id="logResults"><div class="empty"><i data-lucide="scan-search"></i><b>Listo para analizar</b><p>ServiceCloud AI detectará severidad, patrones y causas probables.</p></div></aside>
  </div>

  @push('scripts')
    <script>
      // analyzeLogs() del mockup, con el análisis real del servidor.
      (() => {
        const { $, scFetch, scToast, escapeHTML, iconos } = SC;
        const resultados = $('#logResults'), entrada = $('#logInput');

        $('#clearLogs').addEventListener('click', () => {
          entrada.value = '';
          resultados.innerHTML = `<div class="empty"><i data-lucide="scan-search"></i><b>Sin logs</b><p>Pega contenido técnico para iniciar.</p></div>`;
          iconos();
        });

        $('#analyzeLogs').addEventListener('click', async () => {
          const logs = entrada.value.trim();
          if (!logs) { scToast('No hay logs', 'Pega al menos una línea antes de analizar.', 'aviso'); return; }
          resultados.innerHTML = `<div class="empty"><div class="loader"><span></span><span></span><span></span></div><b style="margin-top:10px">Analizando con IA...</b><p>Correlacionando errores, latencia y pool de conexiones.</p></div>`;
          const r = await scFetch(entrada.dataset.url, { method: 'POST', body: { logs, organizacion_id: $('#logOrganizacion')?.value || null } });
          if (!r.ok) {
            const errores = r.data?.errors ? Object.values(r.data.errors).flat() : [];
            resultados.innerHTML = `<div class="empty"><i data-lucide="circle-alert"></i><b>No se pudo analizar</b><p>${escapeHTML(errores[0] || (r.status === 429 ? 'Demasiados análisis seguidos. Espera un momento.' : 'Inténtalo nuevamente.'))}</p></div>`;
            iconos(); return;
          }
          const d = r.data;
          const eventos = d.eventos.map(e => `<div class="log-event"><b><span class="level ${e.nivel}">${escapeHTML(e.etiqueta)}</span> ${escapeHTML(e.titulo)}</b><p>${escapeHTML(e.detalle)}</p></div>`).join('');
          const recomendaciones = d.recomendaciones.map((t, i) => `${i + 1}. ${escapeHTML(t)}`).join('<br>');
          resultados.innerHTML = `
            <div class="card-title"><h3>Resultado del análisis</h3><span>Confianza ${d.confianza}% · ${d.lineas} líneas</span></div>
            ${eventos || '<p class="ticket-desc">Sin eventos destacados.</p>'}
            <div class="diagnosis"><h4>Diagnóstico IA</h4><p>${escapeHTML(d.diagnostico)}</p></div>
            <div class="diagnosis"><h4>Recomendaciones</h4><p>${recomendaciones}</p></div>
            <button class="btn btn-primary" style="width:100%;margin-top:12px" id="createFromLog" type="button" data-modal-url="${escapeHTML(d.ticket_url)}" data-modal-titulo="Nuevo ticket"><i data-lucide="ticket-plus"></i> Crear ticket desde análisis</button>`;
          if (d.aviso) scToast('IA en modo simulado', d.aviso, 'aviso');
          iconos();
        });
      })();
    </script>
  @endpush
@endsection
