<?php

namespace App\Models;

use App\Enums\EstadoDocumentoRag;
use App\Models\Concerns\PerteneceAOrganizacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentoRag extends Model
{
    use PerteneceAOrganizacion;

    protected static bool $incluyeGlobales = true;

    const CREATED_AT = 'creado_en';

    const UPDATED_AT = null;

    protected $table = 'documentos_rag';

    protected $fillable = ['organizacion_id', 'articulo_id', 'nombre', 'fuente_uri', 'contenido_hash', 'estado', 'indexado_en'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoDocumentoRag::class,
            'indexado_en' => 'datetime',
        ];
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(ArticuloConocimiento::class, 'articulo_id');
    }

    public function fragmentos(): HasMany
    {
        return $this->hasMany(FragmentoRag::class, 'documento_id');
    }
}
