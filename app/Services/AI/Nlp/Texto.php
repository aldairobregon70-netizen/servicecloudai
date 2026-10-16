<?php

namespace App\Services\AI\Nlp;

use Illuminate\Support\Str;

/** Procesamiento de texto en español para el motor local (sin dependencias externas). */
final class Texto
{
    public const DIMENSIONES = 384;

    private const STOPWORDS = [
        'a', 'al', 'algo', 'alguna', 'algunas', 'alguno', 'algunos', 'ante', 'antes', 'aqui', 'asi', 'aun', 'cada', 'como', 'con', 'contra',
        'cual', 'cuando', 'de', 'del', 'desde', 'donde', 'dos', 'el', 'ella', 'ellas', 'ellos', 'en', 'entre', 'era', 'es', 'esa', 'esas',
        'ese', 'eso', 'esos', 'esta', 'estan', 'estas', 'este', 'esto', 'estos', 'fue', 'ha', 'hace', 'hay', 'la', 'las', 'le', 'les', 'lo',
        'los', 'mas', 'me', 'mi', 'mis', 'muy', 'nos', 'o', 'otra', 'otro', 'para', 'pero', 'por', 'porque', 'que', 'se', 'sea', 'ser',
        'si', 'sin', 'sobre', 'son', 'su', 'sus', 'tambien', 'te', 'tiene', 'todo', 'tu', 'un', 'una', 'uno', 'unos', 'y', 'ya', 'yo',
        'the', 'and', 'for', 'to', 'of', 'in', 'is', 'on', 'hoy', 'luego', 'cuales', 'dame', 'puede', 'puedes', 'favor', 'quiero',
    ];

    /** minúsculas, sin tildes, solo letras/números/espacios. */
    public static function normalizar(string $texto): string
    {
        $texto = Str::lower(Str::ascii($texto));
        $texto = preg_replace('/[^a-z0-9%\/.\s-]+/', ' ', $texto);

        return trim(preg_replace('/\s+/', ' ', $texto));
    }

    /** @return list<string> tokens sin stopwords y con una raíz simple */
    public static function tokens(string $texto): array
    {
        $tokens = [];
        foreach (preg_split('/[\s\/.\-]+/', self::normalizar($texto)) as $palabra) {
            if (strlen($palabra) < 3 || in_array($palabra, self::STOPWORDS, true)) {
                continue;
            }
            $tokens[] = self::raiz($palabra);
        }

        return $tokens;
    }

    /** Raíz aproximada: quita plurales y algunos sufijos frecuentes. */
    public static function raiz(string $palabra): string
    {
        foreach (['amiento', 'imiento', 'aciones', 'iciones', 'mente', 'acion', 'icion', 'ancia', 'encia', 'ando', 'iendo', 'ados', 'idos', 'adas', 'idas'] as $sufijo) {
            if (strlen($palabra) > strlen($sufijo) + 3 && str_ends_with($palabra, $sufijo)) {
                return substr($palabra, 0, -strlen($sufijo));
            }
        }
        if (strlen($palabra) > 5 && str_ends_with($palabra, 'es')) {
            return substr($palabra, 0, -2);
        }
        if (strlen($palabra) > 4 && preg_match('/[aeiou]s$/', $palabra)) {
            return substr($palabra, 0, -1);
        }
        if (strlen($palabra) > 5 && preg_match('/(ar|er|ir)$/', $palabra)) {
            return substr($palabra, 0, -2);
        }
        if (strlen($palabra) > 4 && preg_match('/[aeo]$/', $palabra)) {
            return substr($palabra, 0, -1);
        }

        return $palabra;
    }

    /**
     * Embedding por "hashing trick": bolsa de palabras (y bigramas) con peso log(1+tf),
     * proyectada a 384 dimensiones y normalizada (L2). Determinista.
     *
     * @return list<float>
     */
    public static function embedding(string $texto): array
    {
        $vector = array_fill(0, self::DIMENSIONES, 0.0);
        $tokens = self::tokens($texto);
        $frecuencias = array_count_values($tokens);
        for ($i = 0; $i < count($tokens) - 1; $i++) {
            $bigrama = $tokens[$i].'_'.$tokens[$i + 1];
            $frecuencias[$bigrama] = ($frecuencias[$bigrama] ?? 0) + 0.5;
        }
        foreach ($frecuencias as $termino => $tf) {
            $hash = crc32((string) $termino);
            $indice = $hash % self::DIMENSIONES;
            $signo = (($hash >> 16) & 1) ? 1.0 : -1.0;
            $vector[$indice] += $signo * log(1 + $tf);
        }

        return self::normalizarVector($vector);
    }

    /** @param list<float> $vector */
    public static function normalizarVector(array $vector): array
    {
        $norma = sqrt(array_sum(array_map(fn ($v) => $v * $v, $vector)));

        return $norma > 0 ? array_map(fn ($v) => round($v / $norma, 6), $vector) : $vector;
    }

    /** Similitud coseno entre vectores (normalizados o no). */
    public static function coseno(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n === 0) {
            return 0.0;
        }
        $producto = $normaA = $normaB = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $producto += $a[$i] * $b[$i];
            $normaA += $a[$i] * $a[$i];
            $normaB += $b[$i] * $b[$i];
        }

        return $normaA > 0 && $normaB > 0 ? $producto / (sqrt($normaA) * sqrt($normaB)) : 0.0;
    }

    /** ¿El texto normalizado contiene la frase (por inicio de palabra)? */
    public static function contiene(string $normalizado, string $frase): bool
    {
        return (bool) preg_match('/(^|\s)'.preg_quote($frase, '/').'/', $normalizado);
    }

    /** Estimación simple de tokens para el registro de uso. */
    public static function estimarTokens(string $texto): int
    {
        return (int) ceil(mb_strlen($texto) / 4);
    }
}
