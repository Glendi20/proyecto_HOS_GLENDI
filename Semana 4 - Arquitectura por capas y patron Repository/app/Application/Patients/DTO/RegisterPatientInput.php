<?php

namespace App\Application\Patients\DTO;

/**
 * Datos mínimos para el flujo vertical de este módulo (registro local + vínculo MPI):
 * demográficos + DPI. Datos administrativos/de contacto (teléfono, seguro, dirección)
 * quedan fuera del alcance de esta actividad — ver ESPECIFICACION.md "No alcance".
 */
final class RegisterPatientInput
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $birthDate,
        public readonly string $gender,
        public readonly ?string $dpi,
    ) {
    }
}
