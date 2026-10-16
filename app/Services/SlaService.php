<?php

namespace App\Services;

use App\Enums\HorarioServicio;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Cálculo de tiempos SLA.
 * Horario laboral: lunes a viernes de 08:00 a 18:00 (zona APP_TIMEZONE).
 */
class SlaService
{
    public const INICIO_LABORAL = 8;

    public const FIN_LABORAL = 18;

    /** Suma minutos de servicio a una fecha según el horario de la regla. */
    public function sumarMinutos(DateTimeInterface $inicio, int $minutos, HorarioServicio $horario): CarbonImmutable
    {
        $fecha = CarbonImmutable::instance($inicio);
        if ($horario === HorarioServicio::Continuo) {
            return $fecha->addMinutes($minutos);
        }

        $fecha = $this->siguienteMomentoLaboral($fecha);
        while ($minutos > 0) {
            $finDia = $fecha->setTime(self::FIN_LABORAL, 0);
            $disponibles = (int) $fecha->diffInMinutes($finDia);
            if ($minutos <= $disponibles) {
                return $fecha->addMinutes($minutos);
            }
            $minutos -= $disponibles;
            $fecha = $this->siguienteMomentoLaboral($finDia->addMinute());
        }

        return $fecha;
    }

    /** Minutos de servicio transcurridos entre dos fechas según el horario. */
    public function minutosEntre(DateTimeInterface $desde, DateTimeInterface $hasta, HorarioServicio $horario): int
    {
        $desde = CarbonImmutable::instance($desde);
        $hasta = CarbonImmutable::instance($hasta);
        if ($hasta <= $desde) {
            return 0;
        }
        if ($horario === HorarioServicio::Continuo) {
            return (int) $desde->diffInMinutes($hasta);
        }

        $total = 0;
        $cursor = $this->siguienteMomentoLaboral($desde);
        while ($cursor < $hasta) {
            $finDia = $cursor->setTime(self::FIN_LABORAL, 0);
            $tope = $hasta < $finDia ? $hasta : $finDia;
            $total += (int) $cursor->diffInMinutes($tope);
            $cursor = $this->siguienteMomentoLaboral($finDia->addMinute());
        }

        return $total;
    }

    /** Si la fecha cae fuera de horario, avanza a la siguiente apertura. */
    public function siguienteMomentoLaboral(CarbonImmutable $fecha): CarbonImmutable
    {
        if ($fecha->isWeekend()) {
            return $this->siguienteMomentoLaboral($fecha->addDay()->setTime(self::INICIO_LABORAL, 0));
        }
        if ($fecha->hour < self::INICIO_LABORAL) {
            return $fecha->setTime(self::INICIO_LABORAL, 0);
        }
        if ($fecha->hour >= self::FIN_LABORAL) {
            return $this->siguienteMomentoLaboral($fecha->addDay()->setTime(self::INICIO_LABORAL, 0));
        }

        return $fecha;
    }
}
