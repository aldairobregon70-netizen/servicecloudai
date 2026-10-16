<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavegacionTest extends TestCase
{
    use RefreshDatabase;

    private function como(string $email): static
    {
        return $this->actingAs(Usuario::sinFiltroOrganizacion()->where('email', $email)->firstOrFail());
    }

    public function test_admin_saas_ve_los_18_modulos_y_todos_responden(): void
    {
        $this->como('admin@servicecloud.ai');

        $respuesta = $this->get('/')->assertOk();
        foreach (config('servicecloud.menu') as $item) {
            $respuesta->assertSee('title="'.$item['etiqueta'].'"', false);
            $this->get(route($item['ruta']))->assertOk();
        }
    }

    public function test_layout_replica_el_mockup_con_el_usuario_autenticado(): void
    {
        $this->como('ana.torres@andesmarket.pe')
            ->get('/')
            ->assertOk()
            ->assertSee('AI Engine Online')
            ->assertSee('Buscar tickets, usuarios, activos...', false)
            ->assertSee('Ana Torres')
            ->assertSee('Supervisor')
            ->assertSee('ServiceCloud AI Insights')
            ->assertDontSee('Jorge Espinoza');
    }

    public function test_solicitante_recibe_403_en_organizaciones(): void
    {
        $this->como('lucia.vega@andesmarket.pe');

        $this->get('/organizaciones')->assertForbidden();
        $this->get('/usuarios')->assertForbidden();
        $this->get('/logs')->assertForbidden();
        $this->get('/reportes')->assertForbidden();
        $this->get('/tickets')->assertOk();
    }

    public function test_menu_oculta_modulos_sin_permiso(): void
    {
        $this->como('lucia.vega@andesmarket.pe')
            ->get('/')
            ->assertSee('title="Tickets"', false)
            ->assertSee('title="Asistente IA"', false)
            ->assertDontSee('title="Organizaciones"', false)
            ->assertDontSee('title="Análisis de Logs"', false)
            ->assertDontSee('title="Reportes"', false);

        $this->como('luis.ramirez@novatech.pe')
            ->get('/')
            ->assertSee('title="Análisis de Logs"', false)
            ->assertDontSee('title="SLA"', false)
            ->assertDontSee('title="Usuarios"', false);
    }

    public function test_busqueda_global_respeta_la_organizacion(): void
    {
        $this->como('luis.ramirez@novatech.pe')
            ->getJson('/buscar?q=00452')
            ->assertOk()
            ->assertJson(['resultados' => []]);

        $this->como('ana.torres@andesmarket.pe')
            ->getJson('/buscar?q=00452')
            ->assertOk()
            ->assertJsonPath('resultados.0.titulo', 'TKT-2026-00452');
    }

    public function test_busqueda_de_solicitante_solo_devuelve_sus_tickets(): void
    {
        $resultados = $this->como('daniela.paredes@andesmarket.pe')
            ->getJson('/buscar?q=TKT-2026')
            ->assertOk()
            ->json('resultados');

        $this->assertNotEmpty($resultados);
        $this->assertNotContains('TKT-2026-00452', array_column($resultados, 'titulo')); // es de Lucía Vega
    }

    public function test_dashboard_carga_para_cada_rol(): void
    {
        foreach (['admin@servicecloud.ai', 'rosa.villanueva@andesmarket.pe', 'ana.torres@andesmarket.pe', 'maria.pena@andesmarket.pe', 'lucia.vega@andesmarket.pe'] as $email) {
            $this->como($email)->get('/')->assertOk()->assertSee('Tickets abiertos');
        }
    }
}
