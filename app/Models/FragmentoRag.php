<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FragmentoRag extends Model
{
    public $timestamps = false;

    protected $table = 'fragmentos_rag';

    protected $fillable = ['documento_id', 'numero', 'contenido', 'embedding_modelo', 'embedding_json', 'token_count'];

    protected function casts(): array
    {
        return [
            'embedding_json' => 'array',
            'numero' => 'integer',
            'token_count' => 'integer',
        ];
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoRag::class, 'documento_id');
    }
}
