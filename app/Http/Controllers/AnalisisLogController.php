<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class AnalisisLogController extends Controller
{
    public function index(): View
    {
        return view('modulos.pendiente', ['clave' => 'logs', 'fase' => 5]);
    }
}
