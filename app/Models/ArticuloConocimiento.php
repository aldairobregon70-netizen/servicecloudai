<?php

namespace App\Models;

use App\Enums\EstadoArticulo;
use App\Enums\VisibilidadArticulo;
use App\Models\Concerns\PerteneceAOrganizacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** organizacion_id NULL = artículo público de plataforma. */
class ArticuloConocimiento extends Model
{
    use PerteneceAOrganizacion;

    protected static bool $incluyeGlobales = true;

    const CREATED_AT = 'creado_en';

    const UPDATED_AT = 'actualizado_en';

    protected $table = 'articulos_conocimiento';

    protected $fillable = [
        'organizacion_id', 'codigo', 'categoria', 'titulo', 'resumen', 'contenido', 'autor_id',
        'visibilidad', 'estado', 'vistas', 'version',
    ];

    protected function casts(): array
    {
        return [
            'visibilidad' => VisibilidadArticulo::class,
            'estado' => EstadoArticulo::class,
            'vistas' => 'integer',
            'version' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'codigo';
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'autor_id');
    }

    public function etiquetas(): BelongsToMany
    {
        return $this->belongsToMany(Etiqueta::class, 'articulos_etiquetas', 'articulo_id', 'etiqueta_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoRag::class, 'articulo_id');
    }
}
