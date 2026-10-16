<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ContratoController extends Controller
{
    public function index(): View
    {
        return view('modulos.pendiente', ['clave' => 'contratos', 'fase' => 3]);
    }
}
