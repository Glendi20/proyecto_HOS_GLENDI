<?php

namespace App\Domain\Patients\Mpi;

/**
 * Candidato de emparejamiento devuelto por el repositorio de CENTRAL (mpi_patients)
 * al buscar coincidencias para un paciente local. Ya viene con el score calculado
 * por la infraestructura (comparación de nombre normalizado, fecha de nacimiento,
 * DPI, etc.); el dominio solo interpreta el score, no lo calcula.
 */
final class MpiCandidate
{
    /**
     * @param  array<int, string>  $matchedFields  campos que coincidieron (auditoría/explicabilidad)
     */
    public function __construct(
        public readonly string $mpiPatientId,
        public readonly float $score,
        public readonly array $matchedFields,
    ) {
    }
}
