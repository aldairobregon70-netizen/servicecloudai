<?php

namespace Database\Seeders;

use App\Enums\EstadoTicket;
use App\Enums\Impacto;
use App\Enums\Prioridad;
use App\Enums\Rol as RolCodigo;
use App\Enums\Urgencia;
use App\Models\Activo;
use App\Models\ArticuloConocimiento;
use App\Models\CategoriaTicket;
use App\Models\ClasificacionIa;
use App\Models\ConfiguracionOrganizacion;
use App\Models\Contrato;
use App\Models\EquipoSoporte;
use App\Models\Incidencia;
use App\Models\ModeloIa;
use App\Models\Notificacion;
use App\Models\Organizacion;
use App\Models\PlanSaas;
use App\Models\PoliticaSla;
use App\Models\PreferenciaNotificacion;
use App\Models\Problema;
use App\Models\RecomendacionIa;
use App\Models\Rol;
use App\Models\Servicio;
use App\Models\Solicitud;
use App\Models\Ticket;
use App\Models\TicketAsignacion;
use App\Models\TicketComentario;
use App\Models\TicketHistorial;
use App\Models\TicketSla;
use App\Models\Usuario;
use App\Services\SlaService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de demostración basados en el objeto `defaults` de docs/mockup/index.html,
 * más historial de 7 días para que dashboard y reportes tengan información.
 * Contraseña de todos los usuarios: password
 */
class DemoSeeder extends Seeder
{
    /** @var array<string, Organizacion> clave corta => organización */
    private array $orgs = [];

    /** @var array<string, Usuario> email => usuario */
    private array $usuarios = [];

    /** @var array<string, Servicio> "org:nombre" => servicio */
    private array $servicios = [];

    /** @var array<string, Activo> nombre => activo */
    private array $activos = [];

    /** @var array<string, PoliticaSla> "org:nombre" => política */
    private array $politicas = [];

    /** @var array<string, EquipoSoporte> "org:nombre" => equipo */
    private array $equipos = [];

    private CarbonImmutable $ahora;

    private SlaService $sla;

    public function run(SlaService $sla): void
    {
        $this->sla = $sla;
        $this->ahora = CarbonImmutable::now()->startOfMinute();
        mt_srand(20261003);

        $this->organizaciones();
        $this->usuarios();
        $this->equipos();
        $this->categorias();
        $this->politicasSla();
        $this->contratos();
        $this->servicios();
        $this->activos();
        $this->call(ConocimientoSeeder::class);
        $this->ticketsMockup();
        $this->ticketsHistoricos();
        $this->incidenciasProblemasSolicitudes();
        $this->notificaciones();
    }

    // ---------------------------------------------------------------------
    // Organizaciones y usuarios
    // ---------------------------------------------------------------------

    private function organizaciones(): void
    {
        $planes = PlanSaas::pluck('id', 'nombre');
        $datos = [
            'andes' => ['ORG-001', 'AndesMarket S.A.C.', '20512345671', 'Retail', 'Enterprise', 'activa', 'ti@andesmarket.pe', 'Av. Javier Prado Este 4200, Lima'],
            'pacifico' => ['ORG-002', 'Pacífico Centro Odontológico', '20498765432', 'Salud', 'Business', 'activa', 'sistemas@pacifico.pe', 'Av. Larco 812, Miraflores'],
            'logistica' => ['ORG-003', 'Logística Norte Perú', '20603456789', 'Logística', 'Business', 'activa', 'soporte@logisticanorte.pe', 'Carretera Panamericana Norte km 558, Trujillo'],
            'novatech' => ['ORG-004', 'Grupo NovaTech', '20554433221', 'Tecnología', 'Enterprise', 'activa', 'mesa@novatech.pe', 'Calle Las Begonias 441, San Isidro'],
            'horizonte' => ['ORG-005', 'Instituto Horizonte', '20187654321', 'Educación', 'Starter', 'evaluacion', 'ti@horizonte.edu.pe', 'Av. España 1350, Chimbote'],
        ];

        foreach ($datos as $clave => [$codigo, $razon, $ruc, $sector, $plan, $estado, $email, $direccion]) {
            $this->orgs[$clave] = Organizacion::create([
                'codigo' => $codigo, 'razon_social' => $razon, 'ruc' => $ruc, 'sector' => $sector,
                'plan_id' => $planes[$plan], 'estado' => $estado, 'email' => $email,
                'telefono' => '+51 1 '.mt_rand(200, 799).' '.mt_rand(1000, 9999), 'direccion' => $direccion,
                'creado_en' => $this->ahora->subMonths(mt_rand(4, 10)),
            ]);
            ConfiguracionOrganizacion::create([
                'organizacion_id' => $this->orgs[$clave]->id,
                'clasificacion_automatica' => true,
                'prioridad_automatica' => true,
                'rag_habilitado' => true,
                'zona_horaria' => 'America/Lima',
            ]);
        }
    }

    private function usuarios(): void
    {
        $password = Hash::make('password');
        $roles = Rol::pluck('id', 'codigo');

        $datos = [
            // email, nombre, org, rol, cargo, estado
            ['admin@servicecloud.ai', 'Jorge Espinoza', null, RolCodigo::AdminSaas, 'Administrador de plataforma', 'activo'],

            ['rosa.villanueva@andesmarket.pe', 'Rosa Villanueva', 'andes', RolCodigo::AdminOrganizacion, 'Jefa de TI', 'activo'],
            ['ana.torres@andesmarket.pe', 'Ana Torres', 'andes', RolCodigo::Supervisor, 'Supervisora de mesa de ayuda', 'activo'],
            ['maria.pena@andesmarket.pe', 'María Peña', 'andes', RolCodigo::Tecnico, 'Especialista de aplicaciones', 'activo'],
            ['kevin.rojas@andesmarket.pe', 'Kevin Rojas', 'andes', RolCodigo::Tecnico, 'Técnico de infraestructura', 'activo'],
            ['lucia.vega@andesmarket.pe', 'Lucía Vega', 'andes', RolCodigo::Solicitante, 'Analista comercial', 'activo'],
            ['daniela.paredes@andesmarket.pe', 'Daniela Paredes', 'andes', RolCodigo::Solicitante, 'Asistente de finanzas', 'activo'],

            ['patricia.gomez@novatech.pe', 'Patricia Gómez', 'novatech', RolCodigo::AdminOrganizacion, 'Gerente de TI', 'activo'],
            ['ricardo.flores@novatech.pe', 'Ricardo Flores', 'novatech', RolCodigo::Supervisor, 'Supervisor de operaciones', 'activo'],
            ['luis.ramirez@novatech.pe', 'Luis Ramírez', 'novatech', RolCodigo::Tecnico, 'Técnico de soporte', 'activo'],
            ['carla.medina@novatech.pe', 'Carla Medina', 'novatech', RolCodigo::Tecnico, 'Especialista de redes', 'activo'],
            ['marco.rios@novatech.pe', 'Marco Ríos', 'novatech', RolCodigo::Solicitante, 'Desarrollador', 'activo'],

            ['oscar.mendoza@logisticanorte.pe', 'Óscar Mendoza', 'logistica', RolCodigo::AdminOrganizacion, 'Jefe de sistemas', 'activo'],
            ['diana.paz@logisticanorte.pe', 'Diana Paz', 'logistica', RolCodigo::Tecnico, 'Técnica de soporte', 'activo'],
            ['maria.salazar@logisticanorte.pe', 'María Salazar', 'logistica', RolCodigo::Solicitante, 'Coordinadora de almacén', 'activo'],

            ['sofia.herrera@pacifico.pe', 'Sofía Herrera', 'pacifico', RolCodigo::AdminOrganizacion, 'Coordinadora de sistemas', 'activo'],
            ['hugo.vargas@pacifico.pe', 'Hugo Vargas', 'pacifico', RolCodigo::Tecnico, 'Técnico de soporte', 'activo'],
            ['carmen.lozano@pacifico.pe', 'Carmen Lozano', 'pacifico', RolCodigo::Solicitante, 'Recepcionista', 'activo'],

            ['valeria.nunez@horizonte.edu.pe', 'Valeria Núñez', 'horizonte', RolCodigo::AdminOrganizacion, 'Directora de TI', 'activo'],
            ['diego.campos@horizonte.edu.pe', 'Diego Campos', 'horizonte', RolCodigo::Tecnico, 'Técnico de soporte', 'inactivo'],
            ['andres.soto@horizonte.edu.pe', 'Andrés Soto', 'horizonte', RolCodigo::Solicitante, 'Docente', 'activo'],
        ];

        foreach ($datos as [$email, $nombre, $org, $rol, $cargo, $estado]) {
            $organizacion = $org ? $this->orgs[$org] : null;
            $usuario = Usuario::create([
                'organizacion_id' => $organizacion?->id,
                'nombre' => $nombre,
                'email' => $email,
                'password_hash' => $password,
                'telefono' => '+51 9'.mt_rand(10, 99).' '.mt_rand(100, 999).' '.mt_rand(100, 999),
                'cargo' => $cargo,
                'estado' => $estado,
                'ultimo_acceso' => $estado === 'activo' ? $this->ahora->subMinutes(mt_rand(5, 4000)) : null,
            ]);

            if ($organizacion === null) {
                $usuario->rolesGlobales()->attach($roles[$rol->value]);
            } else {
                $usuario->rolesOrganizacion()->attach($roles[$rol->value], ['organizacion_id' => $organizacion->id]);
            }
            PreferenciaNotificacion::create(['usuario_id' => $usuario->id]);
            $this->usuarios[$email] = $usuario;
        }
    }

    private function u(string $email): Usuario
    {
        return $this->usuarios[$email];
    }

    private function equipos(): void
    {
        $datos = [
            'andes' => ['Mesa de Ayuda' => ['maria.pena@andesmarket.pe', 'kevin.rojas@andesmarket.pe'], 'Equipo Apps' => ['maria.pena@andesmarket.pe'], 'Equipo Infraestructura' => ['kevin.rojas@andesmarket.pe']],
            'novatech' => ['Mesa de Ayuda' => ['luis.ramirez@novatech.pe', 'carla.medina@novatech.pe'], 'Equipo Apps' => ['luis.ramirez@novatech.pe'], 'Equipo Redes' => ['carla.medina@novatech.pe']],
            'logistica' => ['Mesa de Ayuda' => ['diana.paz@logisticanorte.pe']],
            'pacifico' => ['Mesa de Ayuda' => ['hugo.vargas@pacifico.pe']],
            'horizonte' => ['Mesa de Ayuda' => ['diego.campos@horizonte.edu.pe']],
        ];
        foreach ($datos as $org => $equipos) {
            foreach ($equipos as $nombre => $miembros) {
                $equipo = EquipoSoporte::create([
                    'organizacion_id' => $this->orgs[$org]->id,
                    'nombre' => $nombre,
                    'descripcion' => $nombre === 'Mesa de Ayuda' ? 'Primer nivel de atención' : 'Segundo nivel especializado',
                ]);
                $equipo->miembros()->attach(array_map(fn ($e) => $this->u($e)->id, $miembros));
                $this->equipos["$org:$nombre"] = $equipo;
            }
        }
    }

    private function categorias(): void
    {
        $nombres = ['Aplicaciones', 'Redes', 'Accesos', 'Software', 'Infraestructura', 'Hardware', 'Seguridad', 'Bases de datos', 'Cloud'];
        foreach ($this->orgs as $org) {
            foreach ($nombres as $nombre) {
                CategoriaTicket::create(['organizacion_id' => $org->id, 'nombre' => $nombre]);
            }
        }
    }

    private function categoriaId(string $org, string $nombre): ?int
    {
        return CategoriaTicket::where('organizacion_id', $this->orgs[$org]->id)->where('nombre', $nombre)->value('id');
    }

    // ---------------------------------------------------------------------
    // SLA, contratos, servicios y activos
    // ---------------------------------------------------------------------

    private function politicasSla(): void
    {
        // Las FK compuestas exigen que cada organización tenga sus propias políticas
        // (no puede referenciar las plantillas globales): se copian las plantillas.
        $porOrganizacion = [
            'andes' => ['Gold', 'Silver'], 'novatech' => ['Gold', 'Silver'],
            'logistica' => ['Silver', 'Bronze'], 'pacifico' => ['Silver', 'Bronze'], 'horizonte' => ['Bronze'],
        ];
        foreach ($porOrganizacion as $org => $nombres) {
            foreach ($nombres as $nombre) {
                $plantilla = PlataformaSeeder::PLANTILLAS_SLA[$nombre];
                $politica = PoliticaSla::create([
                    'organizacion_id' => $this->orgs[$org]->id,
                    'nombre' => $nombre,
                    'descripcion' => $plantilla['descripcion'],
                ]);
                PlataformaSeeder::crearReglas($politica, $plantilla);
                $this->politicas["$org:$nombre"] = $politica;
            }
            // Política por defecto de la organización: la primera de la lista.
            ConfiguracionOrganizacion::where('organizacion_id', $this->orgs[$org]->id)
                ->update(['configuracion' => json_encode(['politica_sla_defecto_id' => $this->politicas["$org:{$nombres[0]}"]->id])]);
        }
    }

    private function contratos(): void
    {
        $datos = [
            ['andes', 'CTR-001', 'Soporte TI Enterprise 2026', '2026-01-01', '2026-12-31', 84000, 'vigente', 'Gold'],
            ['novatech', 'CTR-002', 'Cloud & Network Managed', '2026-02-15', '2027-02-14', 126000, 'vigente', 'Gold'],
            ['logistica', 'CTR-003', 'Mesa de Ayuda Business', '2026-05-01', '2027-04-30', 48000, 'vigente', 'Silver'],
            ['pacifico', 'CTR-004', 'Soporte Digital Business', '2025-11-01', '2026-10-31', 36000, 'por_vencer', 'Silver'],
            ['horizonte', 'CTR-005', 'Piloto Mesa de Ayuda Educativa', '2026-11-01', '2027-03-31', 6000, 'borrador', 'Bronze'],
        ];
        foreach ($datos as [$org, $codigo, $nombre, $inicio, $fin, $monto, $estado, $sla]) {
            Contrato::create([
                'organizacion_id' => $this->orgs[$org]->id, 'codigo' => $codigo, 'nombre' => $nombre,
                'fecha_inicio' => $inicio, 'fecha_fin' => $fin, 'monto' => $monto, 'moneda' => 'PEN',
                'estado' => $estado, 'politica_sla_id' => $this->politicas["$org:$sla"]->id,
                'descripcion' => 'Cobertura de soporte TI según política SLA '.$sla.'.',
            ]);
        }
    }

    private function servicios(): void
    {
        $datos = [
            // org, código, nombre, responsable, disponibilidad, estado, SLA, contrato, categoría
            ['andes', 'SRV-01', 'ERP Corporativo', 'maria.pena@andesmarket.pe', 99.95, 'operativo', 'Gold', 'CTR-001', 'Aplicaciones'],
            ['andes', 'SRV-04', 'VPN Corporativa', 'kevin.rojas@andesmarket.pe', 99.91, 'operativo', 'Gold', 'CTR-001', 'Redes'],
            ['andes', 'SRV-06', 'Base de datos', 'kevin.rojas@andesmarket.pe', 99.95, 'operativo', 'Gold', 'CTR-001', 'Bases de datos'],
            ['andes', 'SRV-07', 'Workplace', 'kevin.rojas@andesmarket.pe', 99.50, 'operativo', 'Silver', 'CTR-001', 'Software'],
            ['novatech', 'SRV-02', 'CRM Cloud', 'luis.ramirez@novatech.pe', 99.82, 'degradado', 'Gold', 'CTR-002', 'Aplicaciones'],
            ['novatech', 'SRV-08', 'VPN Corporativa', 'carla.medina@novatech.pe', 99.90, 'degradado', 'Gold', 'CTR-002', 'Redes'],
            ['novatech', 'SRV-09', 'Red WAN', 'carla.medina@novatech.pe', 99.95, 'operativo', 'Gold', 'CTR-002', 'Redes'],
            ['logistica', 'SRV-03', 'Correo Microsoft 365', 'diana.paz@logisticanorte.pe', 99.99, 'operativo', 'Silver', 'CTR-003', 'Cloud'],
            ['logistica', 'SRV-10', 'File Server', 'diana.paz@logisticanorte.pe', 99.70, 'operativo', 'Silver', 'CTR-003', 'Infraestructura'],
            ['pacifico', 'SRV-05', 'Portal de Atención', 'hugo.vargas@pacifico.pe', 99.97, 'operativo', 'Silver', 'CTR-004', 'Aplicaciones'],
            ['horizonte', 'SRV-11', 'Aula Virtual', 'valeria.nunez@horizonte.edu.pe', 99.50, 'mantenimiento', 'Bronze', 'CTR-005', 'Aplicaciones'],
        ];
        foreach ($datos as [$org, $codigo, $nombre, $responsable, $disp, $estado, $sla, $contrato, $categoria]) {
            $orgId = $this->orgs[$org]->id;
            $this->servicios["$org:$nombre"] = Servicio::create([
                'organizacion_id' => $orgId, 'codigo' => $codigo, 'nombre' => $nombre,
                'descripcion' => 'Servicio '.$nombre.' de '.$this->orgs[$org]->razon_social.'.',
                'categoria' => $categoria, 'responsable_id' => $this->u($responsable)->id,
                'contrato_id' => Contrato::where('organizacion_id', $orgId)->where('codigo', $contrato)->value('id'),
                'politica_sla_id' => $this->politicas["$org:$sla"]->id,
                'disponibilidad_objetivo' => $disp, 'estado' => $estado,
            ]);
        }
    }

    private function activos(): void
    {
        $datos = [
            // org, código, nombre, tipo, ip, hostname, estado, responsable, servicios, fabricante/modelo, cloud
            ['andes', 'AST-2001', 'DB-PROD-01', 'servidor', '10.20.4.11', 'db-prod-01.andes.local', 'operativo', 'kevin.rojas@andesmarket.pe', ['ERP Corporativo', 'Base de datos'], ['Dell', 'PowerEdge R750'], null],
            ['novatech', 'AST-2002', 'SW-CORE-LIM', 'switch', '10.8.0.2', 'sw-core-lim', 'operativo', 'carla.medina@novatech.pe', ['Red WAN', 'VPN Corporativa'], ['Cisco', 'Catalyst 9300'], null],
            ['andes', 'AST-2003', 'NB-MKT-023', 'laptop', 'DHCP', 'nb-mkt-023', 'asignado', 'lucia.vega@andesmarket.pe', ['Workplace'], ['Lenovo', 'ThinkPad T14'], null],
            ['logistica', 'AST-2004', 'FW-EDGE-01', 'firewall', '172.16.1.1', 'fw-edge-01', 'operativo', 'diana.paz@logisticanorte.pe', ['Correo Microsoft 365', 'File Server'], ['Fortinet', 'FortiGate 100F'], null],
            ['novatech', 'AST-2005', 'APP-CRM-02', 'vm_cloud', '10.8.20.14', 'app-crm-02.novatech.cloud', 'degradado', 'luis.ramirez@novatech.pe', ['CRM Cloud'], ['AWS', 'm6i.xlarge'], ['AWS', 'us-east-1']],
            ['andes', 'AST-2006', 'APP-ERP-01', 'vm_cloud', '10.20.4.20', 'app-erp-01.andes.cloud', 'operativo', 'maria.pena@andesmarket.pe', ['ERP Corporativo'], ['Microsoft', 'Azure D4s v5'], ['Azure', 'brazilsouth']],
            ['andes', 'AST-2007', 'PRN-ADM-01', 'impresora', '10.20.8.40', 'prn-adm-01', 'mantenimiento', 'kevin.rojas@andesmarket.pe', ['Workplace'], ['HP', 'LaserJet M507'], null],
            ['pacifico', 'AST-2008', 'SRV-WEB-PAC', 'servidor', '10.30.1.5', 'web.pacifico.local', 'operativo', 'hugo.vargas@pacifico.pe', ['Portal de Atención'], ['HPE', 'ProLiant DL360'], null],
            ['logistica', 'AST-2009', 'FS-LOG-01', 'servidor', '172.16.2.10', 'fs-log-01', 'operativo', 'diana.paz@logisticanorte.pe', ['File Server'], ['Dell', 'PowerEdge T550'], null],
            ['horizonte', 'AST-2010', 'LMS-VM-01', 'vm_cloud', '10.50.0.8', 'lms.horizonte.edu.pe', 'operativo', 'valeria.nunez@horizonte.edu.pe', ['Aula Virtual'], ['Google', 'e2-standard-4'], ['GCP', 'southamerica-west1']],
        ];
        foreach ($datos as [$org, $codigo, $nombre, $tipo, $ip, $host, $estado, $responsable, $servicios, [$fabricante, $modelo], $cloud]) {
            $orgId = $this->orgs[$org]->id;
            $serviciosIds = array_map(fn ($s) => $this->servicios["$org:$s"]->id, $servicios);
            $activo = Activo::create([
                'organizacion_id' => $orgId, 'codigo' => $codigo, 'nombre' => $nombre, 'tipo' => $tipo,
                'numero_serie' => strtoupper(substr(md5($codigo), 0, 10)), 'fabricante' => $fabricante, 'modelo' => $modelo,
                'ip' => $ip, 'hostname' => $host, 'ubicacion' => $cloud ? 'Nube '.$cloud[0] : 'Sede principal',
                'responsable_id' => $this->u($responsable)->id,
                'servicio_id' => $serviciosIds[0], // columna heredada (compatibilidad)
                'proveedor_cloud' => $cloud[0] ?? null, 'region_cloud' => $cloud[1] ?? null, 'estado' => $estado,
            ]);
            $activo->servicios()->attach(array_fill_keys($serviciosIds, ['organizacion_id' => $orgId]));
            $this->activos[$nombre] = $activo;
        }
    }

    // ---------------------------------------------------------------------
    // Tickets
    // ---------------------------------------------------------------------

    /**
     * Crea un ticket con SLA, historial, asignación y fechas coherentes.
     *
     * @param  array<string, mixed>  $d
     */
    private function crearTicket(array $d): Ticket
    {
        $org = $d['org'];
        $orgId = $this->orgs[$org]->id;
        $creado = $d['creado'];
        $estado = EstadoTicket::from($d['estado']);
        $impacto = Impacto::from($d['impacto'] ?? 'medio');
        $urgencia = Urgencia::from($d['urgencia'] ?? 'media');
        $prioridad = isset($d['prioridad']) ? Prioridad::from($d['prioridad']) : Prioridad::desdeMatriz($impacto, $urgencia);
        $servicio = isset($d['servicio']) ? $this->servicios["$org:{$d['servicio']}"] : null;
        // Servicio → política por defecto de la organización (la primera creada)
        $politica = $servicio?->politicaSla
            ?? collect($this->politicas)->first(fn ($p, $clave) => str_starts_with($clave, "$org:"));
        $regla = $politica->reglas()->where('prioridad', $prioridad->value)->first();
        $horario = $regla->horario_servicio;
        $tecnico = isset($d['tecnico']) ? $this->u($d['tecnico']) : null;
        $solicitante = $this->u($d['solicitante']);

        $vencRespuesta = $this->sla->sumarMinutos($creado, $regla->respuesta_minutos, $horario);
        $vencResolucion = $this->sla->sumarMinutos($creado, $regla->resolucion_minutos, $horario);
        $avanzado = ! in_array($estado, [EstadoTicket::Abierto, EstadoTicket::Cancelado], true);
        $primeraRespuesta = $avanzado ? $creado->addMinutes(max(3, (int) ($regla->respuesta_minutos * ($d['factor_respuesta'] ?? 0.5)))) : null;
        if ($primeraRespuesta && $primeraRespuesta > $this->ahora) {
            // Ningún evento demo puede quedar en el futuro
            $primeraRespuesta = $this->ahora->subMinute()->max($creado->addMinutes(3));
        }
        $resuelto = in_array($estado, [EstadoTicket::Resuelto, EstadoTicket::Cerrado], true)
            ? ($d['resuelto'] ?? $creado->addMinutes((int) ($regla->resolucion_minutos * ($d['factor_resolucion'] ?? 0.6))))
            : null;
        if ($resuelto && $resuelto > $this->ahora) {
            $resuelto = $this->ahora->subMinutes(5);
        }
        $cerrado = $estado === EstadoTicket::Cerrado && $resuelto ? $resuelto->addHours(2)->min($this->ahora) : null;

        $ticket = Ticket::create([
            'organizacion_id' => $orgId,
            'codigo' => $d['codigo'],
            'titulo' => $d['titulo'],
            'descripcion' => $d['descripcion'] ?? $d['titulo'],
            'tipo' => $d['tipo'] ?? 'incidente',
            'categoria' => $d['categoria'],
            'categoria_id' => $this->categoriaId($org, $d['categoria']),
            'prioridad' => $prioridad,
            'impacto' => $impacto,
            'urgencia' => $urgencia,
            'estado' => $estado,
            'origen' => $d['origen'] ?? 'portal',
            'solicitante_id' => $solicitante->id,
            'tecnico_id' => $tecnico?->id,
            'equipo_id' => isset($d['equipo']) ? $this->equipos["$org:{$d['equipo']}"]->id : null,
            'servicio_id' => $servicio?->id,
            'activo_id' => isset($d['activo']) ? $this->activos[$d['activo']]->id : null,
            'politica_sla_id' => $politica->id,
            'primera_respuesta_en' => $primeraRespuesta,
            'vencimiento_respuesta' => $vencRespuesta,
            'vencimiento_resolucion' => $vencResolucion,
            'resuelto_en' => $resuelto,
            'cerrado_en' => $cerrado,
            'solucion' => $resuelto ? ($d['solucion'] ?? 'Se aplicó el procedimiento de la base de conocimiento y el usuario confirmó la solución.') : null,
            'creado_en' => $creado,
            'actualizado_en' => $cerrado ?? $resuelto ?? $primeraRespuesta ?? $creado,
        ]);

        $pausados = $estado === EstadoTicket::EnEspera ? 45 : 0;
        TicketSla::create([
            'ticket_id' => $ticket->id,
            'regla_sla_id' => $regla->id,
            'respuesta_cumplida' => $primeraRespuesta ? $primeraRespuesta <= $vencRespuesta : ($this->ahora > $vencRespuesta ? false : null),
            'resolucion_cumplida' => $resuelto ? $resuelto <= $vencResolucion : ($this->ahora > $vencResolucion ? false : null),
            'minutos_pausados' => $pausados,
            'ultima_pausa_en' => $estado === EstadoTicket::EnEspera ? $this->ahora->subMinutes($pausados) : null,
            'calculado_en' => $this->ahora,
        ]);

        // Historial y asignación
        TicketHistorial::create(['ticket_id' => $ticket->id, 'usuario_id' => $solicitante->id, 'campo' => 'creacion', 'valor_anterior' => null, 'valor_nuevo' => 'Ticket creado desde '.($ticket->origen->label()), 'fecha' => $creado]);
        $asignador = $this->supervisorDe($org);
        if ($tecnico) {
            $fechaAsignacion = $creado->addMinutes(2);
            TicketAsignacion::create(['ticket_id' => $ticket->id, 'tecnico_anterior_id' => null, 'tecnico_nuevo_id' => $tecnico->id, 'equipo_id' => $ticket->equipo_id, 'asignado_por_id' => $asignador?->id, 'motivo' => 'Asignación inicial por especialidad', 'fecha' => $fechaAsignacion]);
            TicketHistorial::create(['ticket_id' => $ticket->id, 'usuario_id' => $asignador?->id, 'campo' => 'tecnico', 'valor_anterior' => null, 'valor_nuevo' => $tecnico->nombre, 'fecha' => $fechaAsignacion]);
            TicketHistorial::create(['ticket_id' => $ticket->id, 'usuario_id' => $asignador?->id, 'campo' => 'estado', 'valor_anterior' => 'abierto', 'valor_nuevo' => 'asignado', 'fecha' => $fechaAsignacion]);
        }
        $cadena = ['en_progreso' => $primeraRespuesta, 'en_espera' => $primeraRespuesta?->addMinutes(20)->min($this->ahora), 'resuelto' => $resuelto, 'cerrado' => $cerrado];
        $anterior = 'asignado';
        foreach ($cadena as $valor => $fecha) {
            if (! $fecha || ! $this->estadoAlcanza($estado, $valor)) {
                continue;
            }
            TicketHistorial::create(['ticket_id' => $ticket->id, 'usuario_id' => ($valor === 'cerrado' ? $asignador : $tecnico)?->id, 'campo' => 'estado', 'valor_anterior' => $anterior, 'valor_nuevo' => $valor, 'fecha' => $fecha]);
            $anterior = $valor;
        }

        return $ticket;
    }

    /** ¿El ticket pasó por este estado para llegar al actual? */
    private function estadoAlcanza(EstadoTicket $actual, string $estado): bool
    {
        $orden = ['asignado' => 1, 'en_progreso' => 2, 'en_espera' => 3, 'resuelto' => 4, 'cerrado' => 5];
        $posActual = $orden[$actual->value] ?? 0;
        if ($estado === 'en_espera') {
            return $actual === EstadoTicket::EnEspera;
        }

        return ($orden[$estado] ?? 99) <= $posActual;
    }

    private function supervisorDe(string $org): ?Usuario
    {
        foreach ($this->usuarios as $usuario) {
            if ($usuario->organizacion_id === $this->orgs[$org]->id && $usuario->tieneRol(RolCodigo::Supervisor, RolCodigo::AdminOrganizacion)) {
                return $usuario;
            }
        }

        return null;
    }

    /** Los tickets del mockup, con fechas relativas a "ahora" para que el SLA restante coincida. */
    private function ticketsMockup(): void
    {
        $t = $this->ahora;

        $caso = $this->crearTicket([
            'org' => 'andes', 'codigo' => 'TKT-2026-00452',
            'titulo' => 'La aplicación demora demasiado y finalmente muestra error al consultar clientes.',
            'descripcion' => "La aplicación demora demasiado y finalmente muestra error al consultar clientes.\n\nDesde las 20:30 el módulo de clientes del ERP tarda más de 30 segundos en responder y luego muestra \"Error al consultar clientes\". Afecta al equipo comercial completo durante el cierre del día.",
            'categoria' => 'Aplicaciones', 'servicio' => 'ERP Corporativo', 'activo' => 'APP-ERP-01', 'equipo' => 'Equipo Apps',
            'impacto' => 'alto', 'urgencia' => 'media', 'estado' => 'en_progreso',
            'tecnico' => 'maria.pena@andesmarket.pe', 'solicitante' => 'lucia.vega@andesmarket.pe',
            'creado' => $t->subMinutes(168), 'factor_respuesta' => 0.4,
        ]);
        TicketComentario::create(['ticket_id' => $caso->id, 'autor_id' => $this->u('ana.torres@andesmarket.pe')->id, 'contenido' => 'Añadió evidencia: el error aparece al consultar clientes con historial superior a 12 meses.', 'es_interno' => false, 'creado_en' => $t->subMinutes(120)]);
        TicketComentario::create(['ticket_id' => $caso->id, 'autor_id' => $this->u('maria.pena@andesmarket.pe')->id, 'contenido' => 'Tomó la asignación y comenzó el diagnóstico técnico. El pool de conexiones de APP-ERP-01 está al 92 %.', 'es_interno' => true, 'creado_en' => $t->subMinutes(95)]);
        $this->iaParaCaso($caso);

        $this->crearTicket([
            'org' => 'novatech', 'codigo' => 'TKT-2026-00451', 'titulo' => 'VPN se desconecta al iniciar sesión remota',
            'descripcion' => 'Varios colaboradores reportan que la VPN se desconecta a los pocos segundos de iniciar sesión remota desde casa. El problema comenzó esta tarde.',
            'categoria' => 'Redes', 'servicio' => 'VPN Corporativa', 'equipo' => 'Equipo Redes',
            'impacto' => 'alto', 'urgencia' => 'alta', 'estado' => 'asignado',
            'tecnico' => 'carla.medina@novatech.pe', 'solicitante' => 'marco.rios@novatech.pe', 'creado' => $t->subMinutes(99),
        ]);
        $this->crearTicket([
            'org' => 'logistica', 'codigo' => 'TKT-2026-00450', 'titulo' => 'Usuario sin acceso a carpeta compartida',
            'descripcion' => 'No puedo abrir la carpeta compartida de Almacén en el File Server; aparece "Acceso denegado" desde ayer.',
            'categoria' => 'Accesos', 'servicio' => 'File Server', 'tipo' => 'solicitud',
            'impacto' => 'medio', 'urgencia' => 'media', 'estado' => 'en_espera',
            'tecnico' => 'diana.paz@logisticanorte.pe', 'solicitante' => 'maria.salazar@logisticanorte.pe', 'creado' => $t->subMinutes(616),
        ]);
        $this->crearTicket([
            'org' => 'novatech', 'codigo' => 'TKT-2026-00449', 'titulo' => 'Alta latencia en CRM durante cierre comercial',
            'descripcion' => 'El CRM Cloud responde con más de 5 segundos de latencia al generar reportes de pipeline durante el cierre comercial.',
            'categoria' => 'Aplicaciones', 'servicio' => 'CRM Cloud', 'activo' => 'APP-CRM-02', 'equipo' => 'Equipo Apps',
            'impacto' => 'medio', 'urgencia' => 'alta', 'estado' => 'en_progreso',
            'tecnico' => 'luis.ramirez@novatech.pe', 'solicitante' => 'marco.rios@novatech.pe', 'creado' => $t->subMinutes(87),
        ]);
        $this->crearTicket([
            'org' => 'andes', 'codigo' => 'TKT-2026-00448', 'titulo' => 'Solicitud de instalación de software contable',
            'descripcion' => 'Se requiere instalar el software contable Concar en la laptop del área de finanzas.',
            'categoria' => 'Software', 'servicio' => 'Workplace', 'tipo' => 'solicitud',
            'impacto' => 'bajo', 'urgencia' => 'baja', 'estado' => 'resuelto',
            'tecnico' => 'maria.pena@andesmarket.pe', 'solicitante' => 'daniela.paredes@andesmarket.pe', 'creado' => $t->subMinutes(300),
            'solucion' => 'Software instalado y licenciado. El usuario validó el acceso.',
        ]);
        $this->crearTicket([
            'org' => 'andes', 'codigo' => 'TKT-2026-00447', 'titulo' => 'Alerta de espacio en disco del servidor DB-PROD-01',
            'descripcion' => 'El monitoreo reporta 91 % de uso en el volumen de datos del servidor DB-PROD-01.',
            'categoria' => 'Infraestructura', 'servicio' => 'Base de datos', 'activo' => 'DB-PROD-01', 'origen' => 'monitorizacion',
            'impacto' => 'alto', 'urgencia' => 'media', 'estado' => 'abierto',
            'solicitante' => 'ana.torres@andesmarket.pe', 'creado' => $t->subMinutes(129),
        ]);
        $this->crearTicket([
            'org' => 'pacifico', 'codigo' => 'TKT-2026-00453', 'titulo' => 'Portal de citas no carga imágenes de radiografías',
            'descripcion' => 'Los pacientes no pueden ver las imágenes de radiografías en el portal de atención.',
            'categoria' => 'Aplicaciones', 'servicio' => 'Portal de Atención', 'activo' => 'SRV-WEB-PAC',
            'impacto' => 'medio', 'urgencia' => 'media', 'estado' => 'en_progreso',
            'tecnico' => 'hugo.vargas@pacifico.pe', 'solicitante' => 'carmen.lozano@pacifico.pe', 'creado' => $t->subMinutes(70),
        ]);
        $this->crearTicket([
            'org' => 'andes', 'codigo' => 'TKT-2026-00454', 'titulo' => 'Error de sincronización OneDrive',
            'descripcion' => 'OneDrive muestra "No se pudo sincronizar" en la carpeta de reportes del área de finanzas.',
            'categoria' => 'Software', 'servicio' => 'Workplace',
            'impacto' => 'medio', 'urgencia' => 'media', 'estado' => 'abierto',
            'solicitante' => 'daniela.paredes@andesmarket.pe', 'creado' => $t->subMinutes(25),
        ]);
        $this->crearTicket([
            'org' => 'andes', 'codigo' => 'TKT-2026-00455', 'titulo' => 'Permiso para carpeta financiera',
            'descripcion' => 'Solicito permiso de lectura para la carpeta compartida Finanzas/Presupuestos 2027.',
            'categoria' => 'Accesos', 'servicio' => 'Workplace', 'tipo' => 'solicitud',
            'impacto' => 'medio', 'urgencia' => 'media', 'estado' => 'asignado',
            'tecnico' => 'kevin.rojas@andesmarket.pe', 'solicitante' => 'lucia.vega@andesmarket.pe', 'creado' => $t->subMinutes(15),
        ]);
    }

    /** Clasificación y recomendación IA del caso de ejemplo obligatorio. */
    private function iaParaCaso(Ticket $ticket): void
    {
        $clasificador = ModeloIa::where('tipo', 'clasificacion')->where('proveedor', 'simulado')->value('id');
        $generativo = ModeloIa::where('tipo', 'generativo')->where('proveedor', 'simulado')->value('id');

        ClasificacionIa::create([
            'ticket_id' => $ticket->id, 'modelo_id' => $clasificador,
            'categoria_sugerida' => 'Aplicaciones', 'prioridad_sugerida' => 'alta', 'confianza' => 0.94,
            'explicacion' => 'Incidente de rendimiento: términos "demora demasiado", "error" y "consultar clientes" indican degradación de una aplicación empresarial con impacto en usuarios.',
            'aceptada' => true, 'creado_en' => $ticket->creado_en->addMinute(),
        ]);
        $recomendacion = RecomendacionIa::create([
            'ticket_id' => $ticket->id, 'modelo_id' => $generativo,
            'diagnostico' => 'El síntoma encaja con un incidente de rendimiento. Las señales más probables son una consulta SQL lenta y la saturación del pool de conexiones.',
            'causas_probables' => ['consulta SQL lenta', 'saturación del pool de conexiones', 'latencia de red', 'recursos insuficientes', 'dependencia externa lenta'],
            'pasos_sugeridos' => ['Revisar el tiempo de ejecución de la consulta de clientes con EXPLAIN ANALYZE.', 'Medir conexiones activas y en espera del pool.', 'Revisar métricas de CPU y RAM de APP-ERP-01 y DB-PROD-01.', 'Medir la latencia hacia dependencias externas.', 'Aplicar índices o paginación y validar el tiempo de respuesta.'],
            'confianza' => 0.94, 'creado_en' => $ticket->creado_en->addMinutes(2),
        ]);
        $relevancias = ['KB-001' => 0.96, 'KB-002' => 0.91, 'KB-003' => 0.87];
        foreach ($relevancias as $codigo => $relevancia) {
            $recomendacion->fuentes()->attach(ArticuloConocimiento::where('codigo', $codigo)->value('id'), ['relevancia' => $relevancia]);
        }
    }

    /** 100 tickets de los últimos 30 días (46 en la última semana), repartidos entre organizaciones, para dashboard y reportes. */
    private function ticketsHistoricos(): void
    {
        $plantillas = [
            ['Aplicaciones', 'Error al generar reporte de ventas en el ERP', 'medio', 'media'],
            ['Aplicaciones', 'Lentitud al guardar pedidos en el sistema', 'alto', 'media'],
            ['Redes', 'Sin conexión a internet en el piso 3', 'alto', 'alta'],
            ['Redes', 'Wi-Fi de invitados no asigna IP', 'bajo', 'media'],
            ['Accesos', 'Restablecer contraseña de correo corporativo', 'bajo', 'alta'],
            ['Accesos', 'Alta de usuario en el ERP para nuevo ingreso', 'medio', 'media'],
            ['Software', 'Actualizar versión de Office', 'bajo', 'baja'],
            ['Software', 'Instalación de cliente VPN en laptop nueva', 'bajo', 'media'],
            ['Infraestructura', 'Respaldo nocturno falló en el servidor de archivos', 'medio', 'alta'],
            ['Infraestructura', 'Alta utilización de CPU en servidor de aplicaciones', 'alto', 'media'],
            ['Hardware', 'Impresora del área administrativa atasca papel', 'bajo', 'baja'],
            ['Hardware', 'Laptop no enciende después de actualización', 'medio', 'media'],
            ['Seguridad', 'Correo sospechoso de phishing recibido', 'medio', 'alta'],
            ['Seguridad', 'Intentos de acceso fallidos a cuenta administrativa', 'alto', 'alta'],
            ['Bases de datos', 'Consulta de inventario tarda más de 20 segundos', 'alto', 'media'],
            ['Cloud', 'Costos inesperados en la cuenta cloud', 'medio', 'baja'],
        ];
        $participantes = [
            'andes' => [['maria.pena@andesmarket.pe', 'kevin.rojas@andesmarket.pe'], ['lucia.vega@andesmarket.pe', 'daniela.paredes@andesmarket.pe'], ['ERP Corporativo', 'Workplace', 'Base de datos', 'VPN Corporativa']],
            'novatech' => [['luis.ramirez@novatech.pe', 'carla.medina@novatech.pe'], ['marco.rios@novatech.pe'], ['CRM Cloud', 'VPN Corporativa', 'Red WAN']],
            'logistica' => [['diana.paz@logisticanorte.pe'], ['maria.salazar@logisticanorte.pe'], ['Correo Microsoft 365', 'File Server']],
            'pacifico' => [['hugo.vargas@pacifico.pe'], ['carmen.lozano@pacifico.pe'], ['Portal de Atención']],
        ];
        $orgsPeso = ['andes', 'andes', 'andes', 'novatech', 'novatech', 'novatech', 'logistica', 'pacifico'];
        $clasificador = ModeloIa::where('tipo', 'clasificacion')->where('proveedor', 'simulado')->value('id');

        for ($i = 0; $i < 100; $i++) {
            $numero = 347 + $i;
            $org = $orgsPeso[mt_rand(0, count($orgsPeso) - 1)];
            [$tecnicos, $solicitantes, $servicios] = $participantes[$org];
            [$categoria, $titulo, $impacto, $urgencia] = $plantillas[mt_rand(0, count($plantillas) - 1)];
            // 20 tickets entre los días 30 y 15, 34 entre los días 14 y 8, y 46 desde el día 7 hasta unas 4 horas atrás
            $minutosAtras = match (true) {
                $i < 20 => 30 * 1440 - $i * (15 * 1440) / 19,
                $i < 54 => 14 * 1440 - ($i - 20) * (6 * 1440) / 33,
                default => 7 * 1440 - ($i - 54) * (7 * 1440 - 240) / 45,
            };
            $creado = $this->ahora->subMinutes((int) $minutosAtras)->subMinutes(mt_rand(0, 50));
            $edadHoras = $this->ahora->diffInHours($creado, true);

            $azar = mt_rand(1, 100);
            $estado = match (true) {
                $edadHoras > 72 => $azar <= 55 ? 'cerrado' : ($azar <= 90 ? 'resuelto' : 'en_progreso'),
                $edadHoras > 24 => $azar <= 50 ? 'resuelto' : ($azar <= 70 ? 'cerrado' : ($azar <= 85 ? 'en_progreso' : 'en_espera')),
                default => $azar <= 30 ? 'resuelto' : ($azar <= 60 ? 'en_progreso' : ($azar <= 80 ? 'asignado' : 'abierto')),
            };
            // ~8 % de los resueltos fuera de SLA para que el cumplimiento no sea 100 %
            $factorResolucion = mt_rand(1, 100) <= 8 ? 1.4 : mt_rand(20, 85) / 100;

            $ticket = $this->crearTicket([
                'org' => $org, 'codigo' => sprintf('TKT-2026-%05d', $numero), 'titulo' => $titulo,
                'descripcion' => $titulo.'. Reportado por el usuario a través del portal de autoservicio.',
                'categoria' => $categoria, 'servicio' => $servicios[mt_rand(0, count($servicios) - 1)],
                'tipo' => in_array($categoria, ['Accesos', 'Software'], true) ? 'solicitud' : 'incidente',
                'impacto' => $impacto, 'urgencia' => $urgencia, 'estado' => $estado,
                'tecnico' => $estado === 'abierto' ? null : $tecnicos[mt_rand(0, count($tecnicos) - 1)],
                'solicitante' => $solicitantes[mt_rand(0, count($solicitantes) - 1)],
                'creado' => $creado, 'factor_resolucion' => $factorResolucion, 'factor_respuesta' => mt_rand(20, 90) / 100,
                'origen' => ['portal', 'portal', 'portal', 'email', 'telefono', 'monitorizacion'][mt_rand(0, 5)],
            ]);

            // Clasificación IA en ~80 % de los tickets; ~90 % aceptadas
            if (mt_rand(1, 100) <= 80) {
                ClasificacionIa::create([
                    'ticket_id' => $ticket->id, 'modelo_id' => $clasificador,
                    'categoria_sugerida' => $categoria, 'prioridad_sugerida' => $ticket->prioridad,
                    'confianza' => mt_rand(72, 97) / 100,
                    'explicacion' => 'Clasificación por palabras clave del título y la descripción.',
                    'aceptada' => mt_rand(1, 100) <= 90, 'creado_en' => $creado->addMinute(),
                ]);
            }
        }
    }

    // ---------------------------------------------------------------------
    // Incidencias, problemas, solicitudes y notificaciones
    // ---------------------------------------------------------------------

    private function incidenciasProblemasSolicitudes(): void
    {
        $andes = $this->orgs['andes']->id;
        $novatech = $this->orgs['novatech']->id;
        $ticket = fn (string $codigo) => Ticket::where('codigo', $codigo)->value('id');

        $prb026 = Problema::create([
            'organizacion_id' => $andes, 'codigo' => 'PRB-026', 'titulo' => 'Saturación recurrente del pool de conexiones',
            'descripcion' => 'Seis tickets en la semana comparten síntomas de lentitud y timeout en el ERP Corporativo.',
            'causa_raiz' => 'En investigación', 'servicio_id' => $this->servicios['andes:ERP Corporativo']->id,
            'responsable_id' => $this->u('maria.pena@andesmarket.pe')->id, 'estado' => 'abierto', 'creado_en' => $this->ahora->subDays(3),
        ]);
        $prb026->tickets()->attach($ticket('TKT-2026-00452'));

        $prb025 = Problema::create([
            'organizacion_id' => $novatech, 'codigo' => 'PRB-025', 'titulo' => 'Pérdida de paquetes en enlace principal',
            'descripcion' => 'Pérdida de paquetes intermitente en el enlace principal que afecta VPN y Red WAN.',
            'causa_raiz' => 'Firmware de edge router', 'solucion_temporal' => 'Conmutar el tráfico VPN al enlace secundario durante los picos.',
            'servicio_id' => $this->servicios['novatech:Red WAN']->id,
            'responsable_id' => $this->u('carla.medina@novatech.pe')->id, 'estado' => 'con_workaround', 'creado_en' => $this->ahora->subDays(6),
        ]);
        $prb025->tickets()->attach($ticket('TKT-2026-00451'));

        $incidencias = [
            [$andes, 'INC-1048', 'TKT-2026-00452', $prb026->id, 'andes:ERP Corporativo', 'Degradación de rendimiento en consultas de clientes', 'alto', 'alta', 'maria.pena@andesmarket.pe', 'investigando', 160, null],
            [$novatech, 'INC-1047', 'TKT-2026-00451', $prb025->id, 'novatech:VPN Corporativa', 'Interrupción parcial del acceso VPN', 'alto', 'media', 'carla.medina@novatech.pe', 'en_progreso', 95, null],
            [$novatech, 'INC-1046', 'TKT-2026-00449', null, 'novatech:CRM Cloud', 'Lentitud en CRM Cloud', 'medio', 'alta', 'luis.ramirez@novatech.pe', 'monitoreando', 80, null],
            [$andes, 'INC-1045', null, null, 'andes:Workplace', 'Fallos intermitentes de impresión', 'bajo', 'baja', 'kevin.rojas@andesmarket.pe', 'resuelta', 2 * 1440, 1440],
        ];
        foreach ($incidencias as [$org, $codigo, $tkt, $problema, $servicio, $descripcion, $impacto, $urgencia, $responsable, $estado, $haceMin, $resueltaHaceMin]) {
            Incidencia::create([
                'organizacion_id' => $org, 'codigo' => $codigo, 'ticket_id' => $tkt ? $ticket($tkt) : null,
                'problema_id' => $problema, 'servicio_id' => $this->servicios[$servicio]->id, 'descripcion' => $descripcion,
                'impacto' => $impacto, 'urgencia' => $urgencia,
                'prioridad' => Prioridad::desdeMatriz(Impacto::from($impacto), Urgencia::from($urgencia)),
                'responsable_id' => $this->u($responsable)->id, 'estado' => $estado,
                'inicio_en' => $this->ahora->subMinutes($haceMin),
                'resuelta_en' => $resueltaHaceMin ? $this->ahora->subMinutes($resueltaHaceMin) : null,
            ]);
        }

        $solicitudes = [
            [$andes, 'REQ-3091', 'Nuevo acceso a Power BI', 'lucia.vega@andesmarket.pe', null, 'media', 'pendiente_aprobacion', 180],
            [$novatech, 'REQ-3090', 'Instalación de Visual Studio Code', 'marco.rios@novatech.pe', 'ricardo.flores@novatech.pe', 'baja', 'en_progreso', 1440],
            [$andes, 'REQ-3089', 'Alta de usuario ERP', 'daniela.paredes@andesmarket.pe', 'ana.torres@andesmarket.pe', 'alta', 'completada', 3 * 1440],
        ];
        foreach ($solicitudes as [$org, $codigo, $titulo, $solicitante, $aprobador, $prioridad, $estado, $haceMin]) {
            $creado = $this->ahora->subMinutes($haceMin);
            Solicitud::create([
                'organizacion_id' => $org, 'codigo' => $codigo, 'titulo' => $titulo,
                'descripcion' => $titulo.' para el desarrollo de mis funciones.',
                'solicitante_id' => $this->u($solicitante)->id, 'aprobador_id' => $aprobador ? $this->u($aprobador)->id : null,
                'prioridad' => $prioridad, 'estado' => $estado,
                'aprobada_en' => $aprobador ? $creado->addHours(2) : null,
                'completada_en' => $estado === 'completada' ? $creado->addDay() : null,
                'creado_en' => $creado,
            ]);
        }
    }

    private function notificaciones(): void
    {
        $tkt = fn (string $codigo) => Ticket::where('codigo', $codigo)->first();
        $t451 = $tkt('TKT-2026-00451');
        $t452 = $tkt('TKT-2026-00452');

        $datos = [
            // usuario, ticket, título, mensaje, tipo, hace (min), leída
            ['admin@servicecloud.ai', $t451, 'SLA próximo a vencer', 'TKT-2026-00451 vence en 21 minutos.', 'sla', 3, false],
            ['admin@servicecloud.ai', null, 'IA detectó un patrón', '6 tickets comparten síntomas de saturación del pool.', 'ia', 11, false],
            ['admin@servicecloud.ai', $t452, 'Ticket asignado', 'TKT-2026-00452 fue asignado a María Peña.', 'asignacion', 22, true],
            ['admin@servicecloud.ai', null, 'Incidencia actualizada', 'INC-1046 pasó a estado Monitoreando.', 'sistema', 34, true],
            ['maria.pena@andesmarket.pe', $t452, 'Ticket asignado', 'Se te asignó TKT-2026-00452.', 'asignacion', 160, false],
            ['ana.torres@andesmarket.pe', $t452, 'IA clasificó un ticket', 'TKT-2026-00452: incidente de rendimiento, prioridad Alta (94 %).', 'ia', 165, false],
            ['lucia.vega@andesmarket.pe', $t452, 'Tu ticket está en progreso', 'TKT-2026-00452 pasó a En progreso.', 'ticket', 100, false],
            ['carla.medina@novatech.pe', $t451, 'SLA próximo a vencer', 'TKT-2026-00451 vence en 21 minutos.', 'sla', 3, false],
            ['luis.ramirez@novatech.pe', $tkt('TKT-2026-00449'), 'Ticket asignado', 'Se te asignó TKT-2026-00449.', 'asignacion', 85, true],
        ];
        foreach ($datos as [$email, $ticket, $titulo, $mensaje, $tipo, $haceMin, $leida]) {
            $usuario = $this->u($email);
            Notificacion::create([
                'organizacion_id' => $usuario->organizacion_id, 'usuario_id' => $usuario->id, 'ticket_id' => $ticket?->id,
                'titulo' => $titulo, 'mensaje' => $mensaje, 'tipo' => $tipo, 'canal' => 'in_app',
                'leida_en' => $leida ? $this->ahora->subMinutes(max(1, $haceMin - 5)) : null,
                'enviada_en' => $this->ahora->subMinutes($haceMin), 'creado_en' => $this->ahora->subMinutes($haceMin),
            ]);
        }
    }
}
