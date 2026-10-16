<?php

namespace Tests\Feature;

use App\Http\Controllers\AnalisisLogController;
use App\Models\AnalisisLogIa;
use App\Models\ArticuloConocimiento;
use App\Models\ClasificacionIa;
use App\Models\ConfiguracionOrganizacion;
use App\Models\ConversacionIa;
use App\Models\DocumentoRag;
use App\Models\Ticket;
use App\Services\AI\AsistenteChat;
use App\Services\AI\Drivers\Gemini;
use App\Services\AI\GestorIA;
use App\Services\AI\Nlp\MotorReglas;
use App\Services\AI\RecomendadorRag;
use App\Services\AI\SolicitudIA;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as PeticionHttp;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ActuaComo;
use Tests\TestCase;

class IaTest extends TestCase
{
    use ActuaComo, RefreshDatabase;

    private const CASO = 'La aplicación demora demasiado y finalmente muestra error al consultar clientes';

    // ------------------------------------------------------------------ clasificación

    public function test_motor_clasifica_el_caso_de_ejemplo(): void
    {
        $resultado = app(MotorReglas::class)->clasificar(self::CASO, self::CASO);

        $this->assertSame('Incidente de rendimiento', $resultado['clasificacion']);
        $this->assertSame('Aplicaciones', $resultado['categoria']);
        $this->assertSame('alta', $resultado['prioridad']);
        $this->assertGreaterThanOrEqual(0.85, $resultado['confianza']);
        $this->assertContains('consulta SQL lenta', $resultado['causas']);
    }

    public function test_caso_de_ejemplo_de_punta_a_punta_al_registrar_el_ticket(): void
    {
        // Un solicitante lo registra con impacto y urgencia medios: la IA lo prioriza como Alta
        $this->como('lucia.vega@andesmarket.pe')->ajax()->post('/tickets', [
            'titulo' => self::CASO, 'descripcion' => self::CASO.'.', 'tipo' => 'incidente', 'impacto' => 'medio', 'urgencia' => 'media',
        ])->assertOk();

        $ticket = Ticket::latest('id')->first();
        $clasificacion = $ticket->clasificaciones()->first();
        $this->assertNotNull($clasificacion, 'La clasificación automática está activa en AndesMarket');
        $this->assertStringStartsWith('Incidente de rendimiento:', $clasificacion->explicacion);
        $this->assertSame('Aplicaciones', $clasificacion->categoria_sugerida);
        $this->assertSame('alta', $clasificacion->prioridad_sugerida->value);
        $this->assertTrue($clasificacion->aceptada); // prioridad_automatica activa
        $this->assertSame('alta', $ticket->prioridad->value);
        $this->assertSame('Aplicaciones', $ticket->nombreCategoria());

        $recomendacion = $ticket->recomendaciones()->with('fuentes')->first();
        $this->assertCount(3, $recomendacion->fuentes);
        $this->assertContains($recomendacion->fuentes->first()->codigo, ['KB-001', 'KB-002', 'KB-007']);

        // El técnico ve el panel con causas y artículos
        $ticket->update(['tecnico_id' => $this->usuario('maria.pena@andesmarket.pe')->id]);
        $this->como('maria.pena@andesmarket.pe')->get("/tickets/{$ticket->codigo}")
            ->assertOk()->assertSee('Incidente de rendimiento')->assertSee('saturación del pool de conexiones')->assertSee('Optimización de consultas SQL');
    }

    public function test_sin_prioridad_automatica_la_sugerencia_queda_pendiente_y_se_acepta(): void
    {
        $this->como('ana.torres@andesmarket.pe');
        ConfiguracionOrganizacion::where('organizacion_id', $this->usuario('ana.torres@andesmarket.pe')->organizacion_id)->update(['prioridad_automatica' => false]);

        $this->ajax()->post('/tickets', [
            'titulo' => 'VPN se desconecta a todos los usuarios remotos', 'descripcion' => 'Nadie puede conectarse por VPN desde casa.',
            'tipo' => 'incidente', 'impacto' => 'bajo', 'urgencia' => 'baja',
        ])->assertOk();
        $ticket = Ticket::latest('id')->first();
        $clasificacion = $ticket->clasificaciones()->first();

        $this->assertNull($clasificacion->aceptada);
        $this->assertSame('Redes', $clasificacion->categoria_sugerida);
        $this->assertSame('baja', $ticket->prioridad->value);

        $this->patch("/tickets/{$ticket->codigo}/clasificacion/{$clasificacion->id}/aceptar")->assertSessionHas('ok');
        $this->assertTrue($clasificacion->fresh()->aceptada);
        $this->assertSame($clasificacion->prioridad_sugerida, $ticket->fresh()->prioridad);
    }

    public function test_reanalizar_ticket_bajo_demanda(): void
    {
        $ticket = Ticket::where('codigo', 'TKT-2026-00447')->first();
        $antes = ClasificacionIa::where('ticket_id', $ticket->id)->count();

        $this->como('ana.torres@andesmarket.pe')->ajax()->post("/tickets/{$ticket->codigo}/clasificar")->assertOk();
        $this->assertSame($antes + 1, ClasificacionIa::where('ticket_id', $ticket->id)->count());

        $this->como('lucia.vega@andesmarket.pe')->ajax()->post("/tickets/{$ticket->codigo}/clasificar")->assertForbidden();
    }

    // ------------------------------------------------------------------ RAG

    public function test_rag_devuelve_primero_el_articulo_de_conexiones_o_sql(): void
    {
        $andes = $this->usuario('ana.torres@andesmarket.pe')->organizacion_id;
        $resultados = app(RecomendadorRag::class)->buscar(self::CASO, $andes, null, 3);

        $this->assertContains($resultados->first()['articulo']->codigo, ['KB-001', 'KB-002', 'KB-007']);
        $codigos = $resultados->pluck('articulo.codigo')->all();
        $this->assertContains('KB-001', $codigos);
        $this->assertGreaterThan($resultados->last()['relevancia'] - 0.0001, $resultados->first()['relevancia']);
    }

    public function test_rag_respeta_organizacion_y_visibilidad(): void
    {
        $novatech = $this->usuario('luis.ramirez@novatech.pe')->organizacion_id;
        $codigos = app(RecomendadorRag::class)->buscar('pool de conexiones ERP reinicio', $novatech, null, 9)->pluck('articulo.codigo')->all();

        $this->assertNotContains('KB-007', $codigos); // artículo propio de AndesMarket
    }

    public function test_indexacion_crea_fragmentos_con_embeddings_y_comando(): void
    {
        $documento = DocumentoRag::sinFiltroOrganizacion()->whereHas('articulo', fn ($q) => $q->where('codigo', 'KB-001'))->firstOrFail();
        $fragmento = $documento->fragmentos()->first();

        $this->assertSame('indexado', $documento->estado->value);
        $this->assertCount(384, $fragmento->embedding_json);
        $this->assertLessThanOrEqual(1000, mb_strlen($fragmento->contenido));
        $this->assertGreaterThan(0, $fragmento->token_count);

        $this->artisan('ia:indexar --forzar')->assertSuccessful()->expectsOutputToContain('Artículos indexados');
    }

    // ------------------------------------------------------------------ logs

    public function test_analizador_detecta_timeout_y_pool_sobre_80(): void
    {
        $respuesta = $this->como('maria.pena@andesmarket.pe')
            ->postJson('/logs/analizar', ['logs' => AnalisisLogController::EJEMPLO])
            ->assertOk()
            ->assertJsonPath('severidad', 'critical');

        $titulos = array_column($respuesta->json('eventos'), 'titulo');
        $this->assertContains('Database connection timeout', $titulos);
        $this->assertContains('Connection pool usage 92%', $titulos);
        $this->assertStringContainsString('pool de conexiones', $respuesta->json('diagnostico'));
        $this->assertStringContainsString('categoria=Bases%20de%20datos', $respuesta->json('ticket_url'));

        $analisis = AnalisisLogIa::findOrFail($respuesta->json('analisis_id'));
        $this->assertCount(6, $analisis->logs);
        $this->assertSame($this->usuario('maria.pena@andesmarket.pe')->organizacion_id, $analisis->organizacion_id);
    }

    public function test_crear_ticket_desde_analisis_lo_vincula(): void
    {
        $id = $this->como('ana.torres@andesmarket.pe')->postJson('/logs/analizar', ['logs' => AnalisisLogController::EJEMPLO])->json('analisis_id');

        $this->get("/tickets/create?analisis={$id}&titulo=Timeout")->assertOk()->assertSee('name="analisis_id" value="'.$id.'"', false);
        $this->ajax()->post('/tickets', [
            'titulo' => 'Timeout de base de datos detectado por AI Log Analyzer', 'descripcion' => 'Ver análisis.', 'tipo' => 'incidente',
            'impacto' => 'alto', 'urgencia' => 'media', 'analisis_id' => $id,
        ])->assertOk();

        $ticket = Ticket::latest('id')->first();
        $this->assertSame($ticket->id, AnalisisLogIa::find($id)->ticket_id);
    }

    public function test_solicitante_no_accede_a_logs(): void
    {
        $this->como('lucia.vega@andesmarket.pe')->get('/logs')->assertForbidden();
        $this->postJson('/logs/analizar', ['logs' => 'x'])->assertForbidden();
    }

    // ------------------------------------------------------------------ Gemini y fallback

    public function test_si_gemini_falla_se_usa_el_simulado(): void
    {
        config(['servicecloud.ia.driver' => 'gemini', 'servicecloud.ia.gemini.api_key' => 'clave-de-prueba', 'servicecloud.ia.gemini.modelo_texto' => 'gemini-test']);
        Http::fake(['*' => Http::response(['error' => 'caído'], 500)]);
        $gestor = GestorIA::crear();

        $respuesta = $gestor->generar(new SolicitudIA(SolicitudIA::CLASIFICAR, 's', 'u', ['titulo' => self::CASO, 'descripcion' => self::CASO], true));

        $this->assertSame('simulado', $respuesta->proveedor);
        $this->assertTrue($gestor->huboFallback());
        $this->assertSame('Aplicaciones', $respuesta->json()['categoria']);
    }

    public function test_gemini_recibe_solo_texto_anonimizado_y_su_json_se_valida(): void
    {
        config(['servicecloud.ia.driver' => 'gemini', 'servicecloud.ia.gemini.api_key' => 'clave-de-prueba', 'servicecloud.ia.gemini.modelo_texto' => 'gemini-test']);
        Http::fake(['*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode(['clasificacion' => 'Incidente de red', 'categoria' => 'Redes', 'prioridad' => 'urgentisima', 'confianza' => 0.8])]]]]],
            'usageMetadata' => ['promptTokenCount' => 120, 'candidatesTokenCount' => 40],
        ])]);
        $gemini = new Gemini(config('servicecloud.ia.gemini'));

        $respuesta = $gemini->generar(new SolicitudIA(SolicitudIA::CLASIFICAR, 'sistema', 'Escribir a lucia.vega@andesmarket.pe o al 987 654 321', ['secreto' => 'no-enviar'], true));

        $this->assertSame('gemini', $respuesta->proveedor);
        $this->assertSame(120, $respuesta->tokensEntrada);
        Http::assertSent(function (PeticionHttp $peticion) {
            $cuerpo = $peticion->body();

            return str_contains($peticion->url(), 'gemini-test:generateContent')
                && $peticion->hasHeader('x-goog-api-key', 'clave-de-prueba')
                && ! str_contains($cuerpo, 'lucia.vega@andesmarket.pe')
                && ! str_contains($cuerpo, '987 654 321')
                && ! str_contains($cuerpo, 'no-enviar');
        });
    }

    // ------------------------------------------------------------------ asistente

    public function test_asistente_usa_el_ticket_citado_y_guarda_la_conversacion(): void
    {
        $respuesta = $this->como('ana.torres@andesmarket.pe')
            ->postJson('/asistente/mensajes', ['mensaje' => 'Analiza el ticket TKT-2026-00452 y dame las causas más probables.'])
            ->assertOk();

        $this->assertStringContainsString('TKT-2026-00452', $respuesta->json('html'));
        $this->assertStringContainsString('consulta SQL lenta', $respuesta->json('html'));
        $conversacion = ConversacionIa::findOrFail($respuesta->json('conversacion_id'));
        $this->assertCount(2, $conversacion->mensajes);
        $this->assertSame($conversacion->ticket_id, Ticket::where('codigo', 'TKT-2026-00452')->value('id'));

        $this->get('/asistente')->assertOk()->assertSee('Analiza el ticket TKT-2026-00452');
    }

    public function test_asistente_no_usa_tickets_de_otra_organizacion(): void
    {
        $html = $this->como('luis.ramirez@novatech.pe')
            ->postJson('/asistente/mensajes', ['mensaje' => 'Analiza el ticket TKT-2026-00452'])
            ->assertOk()->json('html');

        $this->assertStringNotContainsString('demora demasiado', $html);
        $this->assertStringNotContainsString('Diagnóstico sugerido para TKT-2026-00452', $html);
    }

    public function test_asistente_responde_sobre_sla_en_riesgo(): void
    {
        $html = $this->como('ana.torres@andesmarket.pe')->postJson('/asistente/mensajes', ['mensaje' => '¿Qué tickets tienen mayor riesgo de incumplir SLA hoy?'])->json('html');
        $this->assertStringContainsString('Riesgo SLA', $html);
        $this->assertStringContainsString('TKT-2026-', $html);
    }

    public function test_respuesta_markdown_escapa_html(): void
    {
        $html = AsistenteChat::aHtml("**Hola** <script>alert('x')</script>");
        $this->assertStringContainsString('<strong>Hola</strong>', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_admin_saas_usa_el_asistente_sin_historial(): void
    {
        $this->como('admin@servicecloud.ai')->postJson('/asistente/mensajes', ['mensaje' => 'Hola'])
            ->assertOk()->assertJsonPath('conversacion_id', null);
    }

    // ------------------------------------------------------------------ base de conocimiento

    public function test_listado_de_conocimiento_y_visibilidad(): void
    {
        $this->como('ana.torres@andesmarket.pe')->get('/conocimiento')->assertOk()
            ->assertSee('Optimización de consultas SQL')->assertSee('ERP Corporativo: reinicio controlado')->assertDontSee('CRM Cloud: lentitud');

        $this->como('luis.ramirez@novatech.pe')->get('/conocimiento')->assertOk()
            ->assertSee('CRM Cloud: lentitud')       // su borrador
            ->assertDontSee('ERP Corporativo: reinicio controlado');

        $this->como('marco.rios@novatech.pe')->get('/conocimiento')->assertOk()->assertDontSee('CRM Cloud: lentitud');
        $this->get('/conocimiento/KB-008')->assertForbidden();
    }

    public function test_tecnico_crea_borrador_y_supervisor_publica_e_indexa(): void
    {
        $contenido = str_repeat('Para liberar espacio en el servidor de archivos se rotan los logs y se mueven los respaldos antiguos. ', 4);
        $this->como('kevin.rojas@andesmarket.pe')->ajax()->post('/conocimiento', [
            'titulo' => 'Liberar espacio en el File Server', 'categoria' => 'Hardware', 'resumen' => 'Procedimiento de limpieza.',
            'contenido' => $contenido, 'visibilidad' => 'organizacion', 'estado' => 'publicado',
        ])->assertUnprocessable()->assertJsonValidationErrors('estado');

        $this->ajax()->post('/conocimiento', [
            'titulo' => 'Liberar espacio en el File Server', 'categoria' => 'Hardware', 'resumen' => 'Procedimiento de limpieza.',
            'contenido' => $contenido, 'visibilidad' => 'organizacion', 'etiquetas' => 'disco, espacio',
        ])->assertOk();
        $articulo = ArticuloConocimiento::where('titulo', 'Liberar espacio en el File Server')->firstOrFail();
        $this->assertSame('borrador', $articulo->estado->value);
        $this->assertSame('KB-009', $articulo->codigo);
        $this->assertFalse(DocumentoRag::where('articulo_id', $articulo->id)->exists());

        $this->como('ana.torres@andesmarket.pe')->ajax()->put("/conocimiento/{$articulo->codigo}", [
            'titulo' => $articulo->titulo, 'categoria' => 'Infraestructura', 'resumen' => $articulo->resumen, 'contenido' => $contenido,
            'visibilidad' => 'organizacion', 'estado' => 'publicado', 'etiquetas' => 'disco',
        ])->assertOk();
        $documento = DocumentoRag::where('articulo_id', $articulo->id)->first();
        $this->assertSame('indexado', $documento->estado->value);
        $this->assertGreaterThan(0, $documento->fragmentos()->count());

        // Archivarlo lo quita del índice
        $this->ajax()->put("/conocimiento/{$articulo->codigo}", [
            'titulo' => $articulo->titulo, 'categoria' => 'Infraestructura', 'resumen' => $articulo->resumen, 'contenido' => $contenido,
            'visibilidad' => 'organizacion', 'estado' => 'archivado',
        ])->assertOk();
        $this->assertFalse(DocumentoRag::where('articulo_id', $articulo->id)->exists());
    }

    public function test_ver_articulo_suma_vistas_y_busqueda_ia(): void
    {
        $articulo = ArticuloConocimiento::where('codigo', 'KB-002')->first();
        $this->como('lucia.vega@andesmarket.pe')->get('/conocimiento/KB-002')->assertOk()->assertSee('pool de conexiones');
        $this->assertSame($articulo->vistas + 1, $articulo->fresh()->vistas);

        $this->get('/conocimiento?q='.urlencode('la consulta de clientes da timeout').'&modo=ia')
            ->assertOk()->assertSee('Búsqueda semántica activa')->assertSee('Relevancia');
    }

    public function test_admin_de_organizacion_no_edita_articulos_publicos(): void
    {
        $this->como('rosa.villanueva@andesmarket.pe')->get('/conocimiento/KB-001/edit')->assertForbidden();
    }
}
