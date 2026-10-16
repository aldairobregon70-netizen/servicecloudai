<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class SlaController extends Controller
{
    public function index(): View
    {
        return view('modulos.pendiente', ['clave' => 'sla', 'fase' => 4]);
    }
}
