<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ProblemaController extends Controller
{
    public function index(): View
    {
        return view('modulos.pendiente', ['clave' => 'problemas', 'fase' => 4]);
    }
}
