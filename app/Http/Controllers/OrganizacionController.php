<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class OrganizacionController extends Controller
{
    public function index(): View
    {
        return view('modulos.pendiente', ['clave' => 'organizaciones', 'fase' => 3]);
    }
}
