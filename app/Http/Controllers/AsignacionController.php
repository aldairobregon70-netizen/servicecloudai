<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class AsignacionController extends Controller
{
    public function index(): View
    {
        return view('modulos.pendiente', ['clave' => 'asignaciones', 'fase' => 4]);
    }
}
