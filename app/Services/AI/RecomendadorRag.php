<?php

namespace App\Services\AI;

use App\Enums\EstadoArticulo;
use App\Enums\Rol;
use App\Models\ArticuloConocimiento;
use App\Models\FragmentoRag;
use App\Models\Usuario;
use App\Services\AI\Nlp\Texto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Búsqueda híbrida sobre la base de conocimiento:
 * full-text de PostgreSQL (índice GIN en español) + similitud coseno de embeddings en PHP.
 */
class RecomendadorRag
{
    private const PESO_SEMANTICO = 0.65;

    private const PESO_TEXTO = 0.35;

    public function __construct(private GestorIA $ia) {}

    /**
     * Artículos más relevantes para un texto.
     *
     * @return Collection<int, array{articulo: ArticuloConocimiento, relevancia: float, fragmento: string, puntaje: float}>
     */
    public function buscar(string $consulta, ?int $organizacionId, ?Usuario $usuario = null, int $limite = 3): Collection
    {
        $consulta = trim($consulta);
        if ($consulta === '') {
            return collect();
        }

        $articulos = $this->articulosVisibles($organizacionId, $usuario)->get(['id', 'organizacion_id', 'codigo', 'categoria', 'titulo', 'resumen', 'vistas', 'actualizado_en']);
        if ($articulos->isEmpty()) {
            return collect();
        }
        $ids = $articulos->pluck('id')->all();

        // 1) Full-text (OR de términos): rango normalizado 0..1
        $rangos = $this->rangoTexto($consulta, $ids);
        $maxRango = max(0.0001, (float) collect($rangos)->max());

        // 2) Semántica: mejor coseno entre la consulta y los fragmentos de cada artículo
        $embedding = $this->ia->embeddings([$consulta]);
        $vector = $embedding['vectores'][0];
        $fragmentos = FragmentoRag::query()
            ->join('documentos_rag', 'documentos_rag.id', '=', 'fragmentos_rag.documento_id')
            ->whereIn('documentos_rag.articulo_id', $ids)
            ->where('documentos_rag.estado', 'indexado')
            ->where('fragmentos_rag.embedding_modelo', $embedding['modelo'])
            ->get(['fragmentos_rag.contenido', 'fragmentos_rag.embedding_json', 'documentos_rag.articulo_id']);

        $semantica = [];
        $mejorFragmento = [];
        foreach ($fragmentos as $fragmento) {
            $similitud = Texto::coseno($vector, $fragmento->embedding_json ?? []);
            if ($similitud > ($semantica[$fragmento->articulo_id] ?? -1)) {
                $semantica[$fragmento->articulo_id] = $similitud;
                $mejorFragmento[$fragmento->articulo_id] = $fragmento->contenido;
            }
        }

        $resultados = $articulos->map(function (ArticuloConocimiento $articulo) use ($rangos, $maxRango, $semantica, $mejorFragmento) {
            $texto = ($rangos[$articulo->id] ?? 0) / $maxRango;
            $sem = max(0, $semantica[$articulo->id] ?? 0);
            $puntaje = $semantica ? self::PESO_SEMANTICO * $sem + self::PESO_TEXTO * $texto : $texto;

            return ['articulo' => $articulo, 'puntaje' => $puntaje, 'fragmento' => $mejorFragmento[$articulo->id] ?? (string) $articulo->resumen];
        })->filter(fn ($r) => $r['puntaje'] > 0.05)->sortByDesc('puntaje')->take($limite)->values();

        // Relevancia mostrada (0..1): relativa al mejor resultado y a su calidad absoluta
        $mejor = (float) ($resultados->first()['puntaje'] ?? 0);
        $calidad = min(1, $mejor / 0.35);

        return $resultados->map(fn ($r) => $r + [
            'relevancia' => round(min(0.99, 0.55 + 0.44 * ($r['puntaje'] / max($mejor, 0.0001)) * $calidad), 2),
        ]);
    }

    /** Artículos publicados que el usuario puede ver: públicos + de su organización (internos solo para personal). */
    public function articulosVisibles(?int $organizacionId, ?Usuario $usuario = null): Builder
    {
        $esPersonal = ! $usuario || $usuario->esAdminSaas() || $usuario->tieneRol(Rol::AdminOrganizacion, Rol::Supervisor, Rol::Tecnico);

        return ArticuloConocimiento::sinFiltroOrganizacion()
            ->where('estado', EstadoArticulo::Publicado)
            ->where(fn (Builder $q) => $q->whereNull('organizacion_id')->when($organizacionId, fn ($w) => $w->orWhere('organizacion_id', $organizacionId)))
            ->when(! $esPersonal, fn ($q) => $q->where('visibilidad', '!=', 'interna'));
    }

    /** @return array<int, float> articulo_id => ts_rank */
    private function rangoTexto(string $consulta, array $ids): array
    {
        $terminos = collect(preg_split('/\s+/', Texto::normalizar($consulta)))
            ->map(fn ($t) => preg_replace('/[^a-z0-9]/', '', $t))
            ->filter(fn ($t) => strlen($t) >= 3)->unique()->take(30)->values();
        if ($terminos->isEmpty()) {
            return [];
        }
        $tsquery = $terminos->join(' | ');

        return DB::table('articulos_conocimiento')
            ->whereIn('id', $ids)
            ->whereRaw("to_tsvector('spanish', coalesce(titulo,'') || ' ' || coalesce(resumen,'') || ' ' || coalesce(contenido,'')) @@ to_tsquery('spanish', ?)", [$tsquery])
            ->selectRaw("id, ts_rank(to_tsvector('spanish', coalesce(titulo,'') || ' ' || coalesce(resumen,'') || ' ' || coalesce(contenido,'')), to_tsquery('spanish', ?)) as rango", [$tsquery])
            ->pluck('rango', 'id')
            ->map(fn ($r) => (float) $r)
            ->all();
    }
}
