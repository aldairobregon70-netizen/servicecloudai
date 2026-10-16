<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ConocimientoController extends Controller
{
    public function index(): View
    {
        return view('modulos.pendiente', ['clave' => 'conocimiento', 'fase' => 5]);
    }
}
