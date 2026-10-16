<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EsquemaTest extends TestCase
{
    use RefreshDatabase;

    private const TABLAS_LARAVEL = ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'personal_access_tokens'];

    public function test_el_esquema_crea_las_45_tablas(): void
    {
        $tablas = collect(DB::select("select table_name from information_schema.tables where table_schema = 'public' and table_type = 'BASE TABLE'"))
            ->pluck('table_name')
            ->diff(self::TABLAS_LARAVEL);

        $this->assertCount(45, $tablas);
        foreach (['tickets', 'usuarios_roles_organizacion', 'categorias_tickets', 'servicios_activos', 'fragmentos_rag'] as $tabla) {
            $this->assertContains($tabla, $tablas);
        }
    }

    public function test_el_esquema_siembra_roles_y_planes(): void
    {
        $this->assertSame(
            ['ADMIN_ORGANIZACION', 'ADMIN_SAAS', 'SOLICITANTE', 'SUPERVISOR', 'TECNICO'],
            DB::table('roles')->orderBy('codigo')->pluck('codigo')->all(),
        );
        $this->assertSame(3, DB::table('planes_saas')->count());
    }

    public function test_la_tabla_users_de_laravel_no_existe(): void
    {
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('users'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('sessions'));
    }
}
