<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ActivoController extends Controller
{
    public function index(): View
    {
        return view('modulos.pendiente', ['clave' => 'activos', 'fase' => 3]);
    }
}
