<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use App\Services\SlaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ticket */
class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $clasificacion = $this->relationLoaded('clasificaciones') ? $this->clasificaciones->sortByDesc('id')->first() : null;

        return [
            'codigo' => $this->codigo,
            'titulo' => $this->titulo,
            'descripcion' => $this->when($request->routeIs('api.tickets.show', 'api.tickets.store'), $this->descripcion),
            'tipo' => $this->tipo->value,
            'categoria' => $this->nombreCategoria(),
            'prioridad' => $this->prioridad->value,
            'impacto' => $this->impacto->value,
            'urgencia' => $this->urgencia->value,
            'estado' => $this->estado->value,
            'origen' => $this->origen->value,
            'organizacion' => $this->whenLoaded('organizacion', fn () => $this->organizacion->razon_social),
            'tecnico' => $this->whenLoaded('tecnico', fn () => $this->tecnico?->nombre),
            'servicio' => $this->whenLoaded('servicio', fn () => $this->servicio?->nombre),
            'sla' => [
                'vence_respuesta' => $this->vencimiento_respuesta?->toIso8601String(),
                'vence_resolucion' => $this->vencimiento_resolucion?->toIso8601String(),
                'restante' => app(SlaService::class)->estadoSla($this->resource)['texto'],
            ],
            'clasificacion_ia' => $clasificacion ? [
                'clasificacion' => strtok((string) $clasificacion->explicacion, ':'),
                'categoria' => $clasificacion->categoria_sugerida,
                'prioridad' => $clasificacion->prioridad_sugerida?->value,
                'confianza' => $clasificacion->confianza,
                'aceptada' => $clasificacion->aceptada,
            ] : null,
            'creado_en' => $this->creado_en?->toIso8601String(),
            'actualizado_en' => $this->actualizado_en?->toIso8601String(),
        ];
    }
}
