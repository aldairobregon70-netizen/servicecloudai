<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Códigos correlativos legibles: ORG-006, AST-2011, SRV-12, CTR-006, INC-1049, KB-009... */
class Codigos
{
    /**
     * Siguiente código para un prefijo, buscando el mayor número existente en toda la tabla
     * (sin filtro de organización, para no repetir códigos entre organizaciones).
     *
     * @param  class-string<Model>  $modelo
     */
    public static function siguiente(string $modelo, string $prefijo, int $digitos, string $columna = 'codigo'): string
    {
        $tabla = (new $modelo)->getTable();
        $maximo = DB::table($tabla)
            ->where($columna, 'like', $prefijo.'-%')
            // ?::int: sin el cast PostgreSQL interpreta el parámetro como patrón regex, no como posición
            ->selectRaw("max(nullif(regexp_replace(substring($columna from ?::int), '[^0-9]', '', 'g'), '')::bigint) as maximo", [strlen($prefijo) + 2])
            ->value('maximo');

        return $prefijo.'-'.str_pad((string) ((int) $maximo + 1), $digitos, '0', STR_PAD_LEFT);
    }
}
