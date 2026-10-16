<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class AsistenteController extends Controller
{
    public function index(): View
    {
        return view('modulos.pendiente', ['clave' => 'asistente', 'fase' => 5]);
    }
}
