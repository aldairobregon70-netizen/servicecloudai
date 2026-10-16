<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class UsuarioController extends Controller
{
    public function index(): View
    {
        return view('modulos.pendiente', ['clave' => 'usuarios', 'fase' => 3]);
    }
}
