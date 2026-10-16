<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Models\Usuario;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Segundos que se reutilizan las métricas (fase 6: 60 s por alcance). */
    public const CACHE_SEGUNDOS = 60;

    public function __invoke(Request $request, DashboardService $dashboard): View
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();

        $datos = Cache::remember(self::claveCache($usuario), self::CACHE_SEGUNDOS, fn () => $dashboard->datos($usuario));

        return view('dashboard', $datos);
    }

    /** Alcance de las métricas: plataforma, organización (gestión) o usuario (técnico/solicitante). */
    public static function claveCache(Usuario $usuario): string
    {
        return match (true) {
            $usuario->esAdminSaas() => 'dashboard:global',
            $usuario->tieneRol(Rol::AdminOrganizacion, Rol::Supervisor) => 'dashboard:org:'.$usuario->organizacion_id,
            default => 'dashboard:usuario:'.$usuario->id,
        };
    }
}
