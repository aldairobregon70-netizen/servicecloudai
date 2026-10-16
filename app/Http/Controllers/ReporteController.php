<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ReporteController extends Controller
{
    public function index(): View
    {
        return view('modulos.pendiente', ['clave' => 'reportes', 'fase' => 6]);
    }
}
