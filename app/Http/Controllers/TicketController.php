<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class TicketController extends Controller
{
    public function index(): View
    {
        return view('modulos.pendiente', ['clave' => 'tickets', 'fase' => 4]);
    }
}
