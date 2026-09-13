<?php

namespace App\Domain\Patients\Exceptions;

use DomainException;

/**
 * RF-03 / regla de dominio: no pueden coexistir dos pacientes con el mismo DPI
 * dentro del mismo tenant (hospital). Es una regla del HOSPITAL local, no de CENTRAL.
 */
final class DuplicateDpiException extends DomainException
{
    public static function forDpi(string $dpi): self
    {
        return new self("Ya existe un paciente registrado con el DPI {$dpi} en este hospital.");
    }
}
