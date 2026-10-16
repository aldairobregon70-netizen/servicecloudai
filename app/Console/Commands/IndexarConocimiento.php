<?php

namespace App\Console\Commands;

use App\Enums\EstadoArticulo;
use App\Models\ArticuloConocimiento;
use App\Services\AI\IndexadorConocimiento;
use Illuminate\Console\Command;

class IndexarConocimiento extends Command
{
    protected $signature = 'ia:indexar {--forzar : Reindexa aunque el contenido no haya cambiado (por ejemplo, al cambiar de proveedor de embeddings)}';

    protected $description = 'Indexa los artículos publicados de la base de conocimiento para RAG (documentos y fragmentos con embeddings)';

    public function handle(IndexadorConocimiento $indexador): int
    {
        $articulos = ArticuloConocimiento::sinFiltroOrganizacion()->where('estado', EstadoArticulo::Publicado)->orderBy('id')->get();
        $barra = $this->output->createProgressBar($articulos->count());
        $fragmentos = 0;

        foreach ($articulos as $articulo) {
            $documento = $indexador->indexar($articulo, (bool) $this->option('forzar'));
            $fragmentos += $documento?->fragmentos()->count() ?? 0;
            $barra->advance();
        }
        $barra->finish();
        $this->newLine();
        $this->info("Artículos indexados: {$articulos->count()} · fragmentos: {$fragmentos}");

        return self::SUCCESS;
    }
}
