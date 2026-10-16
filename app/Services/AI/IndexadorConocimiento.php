<?php

namespace App\Services\AI;

use App\Enums\EstadoArticulo;
use App\Enums\EstadoDocumentoRag;
use App\Models\ArticuloConocimiento;
use App\Models\DocumentoRag;
use App\Models\FragmentoRag;
use App\Services\AI\Nlp\Texto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Indexa artículos publicados para RAG: documentos_rag + fragmentos_rag
 * (trozos de ~800 caracteres con solapamiento, embedding_json y token_count).
 */
class IndexadorConocimiento
{
    public const TAMANO_FRAGMENTO = 800;

    public const SOLAPAMIENTO = 150;

    public function __construct(private GestorIA $ia) {}

    /** Indexa (o desindexa si ya no está publicado). Devuelve el documento o null. */
    public function indexar(ArticuloConocimiento $articulo, bool $forzar = false): ?DocumentoRag
    {
        if ($articulo->estado !== EstadoArticulo::Publicado) {
            $this->desindexar($articulo);

            return null;
        }

        $textoCompleto = $articulo->titulo."\n\n".($articulo->resumen ? $articulo->resumen."\n\n" : '').$articulo->contenido;
        $hash = hash('sha256', $textoCompleto);
        $documento = DocumentoRag::sinFiltroOrganizacion()->where('articulo_id', $articulo->id)->first();
        if (! $forzar && $documento && $documento->contenido_hash === $hash && $documento->estado === EstadoDocumentoRag::Indexado) {
            return $documento;
        }

        $documento ??= new DocumentoRag(['articulo_id' => $articulo->id]);
        $documento->fill([
            'organizacion_id' => $articulo->organizacion_id,
            'nombre' => $articulo->codigo.' · '.$articulo->titulo,
            'fuente_uri' => 'kb://'.$articulo->codigo,
            'contenido_hash' => $hash,
            'estado' => EstadoDocumentoRag::Pendiente,
        ])->save();

        try {
            $fragmentos = $this->fragmentar($textoCompleto);
            $embeddings = $this->ia->embeddings($fragmentos);

            DB::transaction(function () use ($documento, $fragmentos, $embeddings) {
                $documento->fragmentos()->delete();
                foreach ($fragmentos as $i => $contenido) {
                    FragmentoRag::create([
                        'documento_id' => $documento->id,
                        'numero' => $i + 1,
                        'contenido' => $contenido,
                        'embedding_modelo' => $embeddings['modelo'],
                        'embedding_json' => $embeddings['vectores'][$i],
                        'token_count' => Texto::estimarTokens($contenido),
                    ]);
                }
                $documento->update(['estado' => EstadoDocumentoRag::Indexado, 'indexado_en' => now()]);
            });
        } catch (Throwable $e) {
            $documento->update(['estado' => EstadoDocumentoRag::Error]);
            Log::error('IA: no se pudo indexar '.$articulo->codigo, ['error' => $e->getMessage()]);
        }

        return $documento->fresh();
    }

    public function desindexar(ArticuloConocimiento $articulo): void
    {
        DocumentoRag::sinFiltroOrganizacion()->where('articulo_id', $articulo->id)->get()->each(function (DocumentoRag $documento) {
            $documento->fragmentos()->delete();
            $documento->delete();
        });
    }

    /**
     * Trozos de ~800 caracteres respetando párrafos y oraciones, con solapamiento de ~150.
     *
     * @return list<string>
     */
    public function fragmentar(string $texto): array
    {
        $oraciones = preg_split('/(?<=[.!?:])\s+|\n{2,}/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY);
        $fragmentos = [];
        $actual = '';
        foreach ($oraciones as $oracion) {
            $oracion = trim($oracion);
            if ($actual !== '' && mb_strlen($actual) + mb_strlen($oracion) + 1 > self::TAMANO_FRAGMENTO) {
                $fragmentos[] = $actual;
                $actual = mb_substr($actual, -self::SOLAPAMIENTO);
                $actual = ltrim(mb_substr($actual, (int) mb_strpos($actual, ' ')));
            }
            $actual = trim($actual.' '.$oracion);
        }
        if ($actual !== '') {
            $fragmentos[] = $actual;
        }

        return $fragmentos;
    }
}
