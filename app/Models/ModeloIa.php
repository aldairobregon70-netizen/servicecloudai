<?php

namespace App\Models;

use App\Enums\TipoModeloIa;
use Illuminate\Database\Eloquent\Model;

class ModeloIa extends Model
{
    public $timestamps = false;

    protected $table = 'modelos_ia';

    protected $fillable = ['nombre', 'proveedor', 'version', 'tipo', 'activo', 'configuracion'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoModeloIa::class,
            'activo' => 'boolean',
            'configuracion' => 'array',
        ];
    }
}
