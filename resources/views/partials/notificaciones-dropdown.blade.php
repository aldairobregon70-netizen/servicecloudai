<div class="profile-drop-head"><b>Notificaciones</b><span>{{ $notificacionesNoLeidas }} sin leer</span></div>
@forelse ($notificacionesHeader as $notificacion)
  <a class="notif-item {{ $notificacion->leida_en ? '' : 'unread' }}" href="{{ route('notificaciones.abrir', $notificacion) }}">
    <div class="notif-dot" style="{{ $notificacion->leida_en ? 'opacity:.2' : '' }}"></div>
    <div class="result-text"><b>{{ $notificacion->titulo }}</b><span>{{ $notificacion->mensaje }} · {{ \App\Support\Formato::relativo($notificacion->creado_en) }}</span></div>
  </a>
@empty
  <div class="empty"><i data-lucide="bell-off"></i><b>Sin notificaciones</b><p>Te avisaremos de asignaciones, SLA e IA.</p></div>
@endforelse
<a class="drop-action" href="{{ route('notificaciones.index') }}"><i data-lucide="list"></i> Ver todas las notificaciones</a>
