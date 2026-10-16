<?php

namespace App\Providers;

use App\Models\Notificacion;
use App\Models\Usuario;
use App\Services\AI\GestorIA;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Un gestor de IA por petición/tarea (lee AI_DRIVER y recuerda si hubo fallback)
        $this->app->scoped(GestorIA::class, fn () => GestorIA::crear());
    }

    public function boot(): void
    {
        Carbon::setLocale('es');
        Paginator::defaultView('partials.paginacion');

        // Acceso por módulo del menú: middleware can:modulo,<clave> y @can('modulo', '<clave>')
        Gate::define('modulo', fn (Usuario $usuario, string $clave) => $usuario->puedeVerModulo($clave));

        // El login limita sus intentos en LoginController (5 por minuto por correo + IP).
        RateLimiter::for('ia', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        View::composer('layouts.app', function ($view) {
            /** @var Usuario $usuario */
            $usuario = Auth::user();
            $menu = collect(config('servicecloud.menu'))
                ->filter(fn ($item, $clave) => $usuario->puedeVerModulo($clave));

            $notificaciones = Notificacion::de($usuario)->latest('creado_en')->limit(5)->get();

            $view->with([
                'usuarioActual' => $usuario,
                'menu' => $menu,
                'notificacionesHeader' => $notificaciones,
                'notificacionesNoLeidas' => Notificacion::de($usuario)->noLeidas()->count(),
            ]);
        });
    }
}
