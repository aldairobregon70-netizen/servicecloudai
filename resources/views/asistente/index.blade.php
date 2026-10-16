@extends('layouts.app')

@php
    $nombre = \Illuminate\Support\Str::before(auth()->user()->nombre, ' ');
    $acciones = [
        ['ticket-check', 'Analizar ticket', 'Analiza el ticket TKT-2026-00452 y dame las causas más probables.'],
        ['search-check', 'Buscar solución', 'Busca una solución para errores de timeout en aplicaciones empresariales.'],
        ['scroll-text', 'Analizar logs', 'Analiza logs de conexión y detecta anomalías relevantes.'],
        ['stethoscope', 'Generar diagnóstico', 'Genera un diagnóstico técnico a partir del contexto actual.'],
        ['timer', 'Consultar SLA', '¿Qué tickets tienen mayor riesgo de incumplir SLA hoy?'],
    ];
@endphp

@section('content')
  <x-page-header :titulo="config('servicecloud.menu.asistente.titulo')" :descripcion="config('servicecloud.menu.asistente.descripcion')" />

  <div class="grid chat-layout">
    <section class="card chat-card">
      <div class="chat-head">
        <div class="spark"><i data-lucide="bot"></i></div>
        <div style="flex:1"><h3>ServiceCloud AI Assistant</h3><p>Contexto de tickets + base de conocimiento + SLA</p></div>
        @if ($conversacion)
          <a class="icon-mini" href="{{ route('asistente.index', ['nueva' => 1]) }}" title="Nueva conversación"><i data-lucide="message-square-plus"></i></a>
        @endif
      </div>
      <div class="chat-messages" id="chatMessages" data-url="{{ route('asistente.enviar') }}" data-conversacion="{{ $conversacion?->id }}">
        <div class="message"><div class="message-avatar"><i data-lucide="bot"></i></div><div class="bubble">Hola, {{ $nombre }}. Puedo ayudarte a analizar tickets, correlacionar síntomas, buscar soluciones RAG, revisar SLA o interpretar logs. ¿Qué necesitas?</div></div>
        @foreach ($mensajes as $mensaje)
          @if ($mensaje->rol === App\Enums\RolMensajeIa::User)
            <div class="message user"><div class="message-avatar"><i data-lucide="user"></i></div><div class="bubble">{{ $mensaje->contenido }}</div></div>
          @else
            {{-- Markdown del modelo convertido con html_input=escape (regla 03): el HTML que pudiera traer queda escapado --}}
            <div class="message"><div class="message-avatar"><i data-lucide="bot"></i></div><div class="bubble">{!! App\Services\AI\AsistenteChat::aHtml($mensaje->contenido) !!}</div></div>
          @endif
        @endforeach
      </div>
      <form class="chat-input" id="chatForm">
        <textarea class="form-control" id="chatInput" placeholder="Escribe una pregunta para ServiceCloud AI..." maxlength="2000">{{ $prompt }}</textarea>
        <button class="btn btn-primary" id="sendChat" type="submit" title="Enviar"><i data-lucide="send"></i></button>
      </form>
    </section>
    <aside class="card quick-card">
      <div class="card-title"><h3>Acciones rápidas</h3><span>IA operativa</span></div>
      <div class="quick-actions">
        @foreach ($acciones as [$icono, $etiqueta, $promptRapido])
          <button class="quick-action" type="button" data-prompt="{{ $promptRapido }}"><i data-lucide="{{ $icono }}"></i><span>{{ $etiqueta }}</span><i data-lucide="chevron-right"></i></button>
        @endforeach
      </div>
      <div class="status-chip"><b>RAG activo</b><span>Base de conocimiento conectada · {{ number_format($fragmentos) }} fragmentos indexados</span></div>
      <div class="status-chip" style="margin-top:8px"><b>Contexto ITSM conectado</b><span>Tickets, incidentes, activos, servicios y SLA disponibles · motor {{ $proveedor }}</span></div>
    </aside>
  </div>

  @push('scripts')
    <script>
      // sendChat() del mockup: POST con CSRF e indicador .typing mientras responde el servidor.
      (() => {
        const { $, $$, scFetch, scToast, escapeHTML, iconos } = SC;
        const caja = $('#chatMessages'), input = $('#chatInput');
        caja.scrollTop = caja.scrollHeight;

        async function enviar() {
          const pregunta = input.value.trim();
          if (!pregunta) return;
          caja.insertAdjacentHTML('beforeend', `<div class="message user"><div class="message-avatar"><i data-lucide="user"></i></div><div class="bubble">${escapeHTML(pregunta)}</div></div>`);
          input.value = '';
          caja.insertAdjacentHTML('beforeend', `<div class="message" id="typingMsg"><div class="message-avatar"><i data-lucide="bot"></i></div><div class="bubble"><span class="typing"><i></i><i></i><i></i></span> Analizando contexto ITSM y base RAG...</div></div>`);
          caja.scrollTop = caja.scrollHeight; iconos();
          try {
            const r = await scFetch(caja.dataset.url, { method: 'POST', body: { mensaje: pregunta, conversacion_id: caja.dataset.conversacion || null } });
            $('#typingMsg')?.remove();
            if (!r.ok) {
              const texto = r.status === 429 ? 'Demasiadas consultas seguidas. Espera un momento.' : (r.data?.message || 'No se pudo obtener respuesta.');
              scToast('Asistente IA', texto, 'error');
              return;
            }
            if (r.data.conversacion_id) caja.dataset.conversacion = r.data.conversacion_id;
            // r.data.html viene de Str::markdown con html_input=escape en el servidor
            caja.insertAdjacentHTML('beforeend', `<div class="message"><div class="message-avatar"><i data-lucide="bot"></i></div><div class="bubble">${r.data.html}</div></div>`);
            if (r.data.aviso) scToast('IA en modo simulado', r.data.aviso, 'aviso');
          } catch (e) {
            $('#typingMsg')?.remove();
            scToast('Error de conexión', 'No se pudo contactar al asistente.', 'error');
          }
          caja.scrollTop = caja.scrollHeight; iconos();
        }

        $('#chatForm').addEventListener('submit', e => { e.preventDefault(); enviar(); });
        input.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); enviar(); } });
        $$('.quick-action').forEach(b => b.addEventListener('click', () => { input.value = b.dataset.prompt; enviar(); }));
        if (input.value.trim()) input.focus();
      })();
    </script>
  @endpush
@endsection
