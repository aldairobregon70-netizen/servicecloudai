<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class IncidenciaController extends Controller
{
    public function index(): View
    {
        return view('modulos.pendiente', ['clave' => 'incidencias', 'fase' => 4]);
    }
}
