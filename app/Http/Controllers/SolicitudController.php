<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class SolicitudController extends Controller
{
    public function index(): View
    {
        return view('modulos.pendiente', ['clave' => 'solicitudes', 'fase' => 4]);
    }
}
