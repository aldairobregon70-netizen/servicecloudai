<?php

use App\Http\Controllers\ActivoController;
use App\Http\Controllers\AnalisisLogController;
use App\Http\Controllers\AsignacionController;
use App\Http\Controllers\AsistenteController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BusquedaController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\ConocimientoController;
use App\Http\Controllers\ContratoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IncidenciaController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\OrganizacionController;
use App\Http\Controllers\ProblemaController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\SlaController;
use App\Http\Controllers\SolicitudController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

// --- Autenticación ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// --- Aplicación ---
Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/buscar', BusquedaController::class)->name('buscar');

    // Cada módulo exige el permiso de su entrada de menú (config/servicecloud.php);
    // las acciones concretas las autorizan las Policies.

    // Maestros (CRUD con exportación CSV)
    $maestros = [
        'organizaciones' => [OrganizacionController::class, 'organizacion'],
        'usuarios' => [UsuarioController::class, 'usuario'],
        'activos' => [ActivoController::class, 'activo'],
        'servicios' => [ServicioController::class, 'servicio'],
        'contratos' => [ContratoController::class, 'contrato'],
    ];
    foreach ($maestros as $clave => [$controlador, $parametro]) {
        Route::middleware("can:modulo,'$clave'")->group(function () use ($clave, $controlador, $parametro) {
            Route::get("/$clave/exportar", [$controlador, 'exportar'])->name("$clave.exportar");
            Route::resource($clave, $controlador)->parameters([$clave => $parametro]);
        });
    }

    // Módulos pendientes de las siguientes fases (solo listado)
    $modulos = [
        'tickets' => TicketController::class,
        'incidencias' => IncidenciaController::class,
        'problemas' => ProblemaController::class,
        'solicitudes' => SolicitudController::class,
        'sla' => SlaController::class,
        'asignaciones' => AsignacionController::class,
        'conocimiento' => ConocimientoController::class,
        'asistente' => AsistenteController::class,
        'logs' => AnalisisLogController::class,
        'notificaciones' => NotificacionController::class,
        'reportes' => ReporteController::class,
        'configuracion' => ConfiguracionController::class,
    ];
    foreach ($modulos as $clave => $controlador) {
        Route::middleware("can:modulo,'$clave'")->group(function () use ($clave, $controlador) {
            Route::get("/$clave", [$controlador, 'index'])->name("$clave.index");
        });
    }
});
