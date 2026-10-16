<?php

use Illuminate\Support\Facades\Schedule;

// Tareas programadas (las ejecuta el servicio `scheduler` del compose con schedule:work)
Schedule::command('contratos:actualizar-estados')->dailyAt('00:10');
