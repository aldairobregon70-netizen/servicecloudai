<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ServicioController extends Controller
{
    public function index(): View
    {
        return view('modulos.pendiente', ['clave' => 'servicios', 'fase' => 3]);
    }
}
