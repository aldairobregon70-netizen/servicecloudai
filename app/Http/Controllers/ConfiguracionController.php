<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ConfiguracionController extends Controller
{
    public function index(): View
    {
        return view('modulos.pendiente', ['clave' => 'configuracion', 'fase' => 6]);
    }
}
