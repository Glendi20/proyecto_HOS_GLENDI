<?php

namespace App\Application\Patients\Ports;

use App\Domain\Patients\Mpi\MpiCandidate;
use App\Domain\Patients\ValueObjects\PatientDemographics;

/**
 * Puerto hacia el Master Patient Index (CENTRAL). Las implementaciones deben
 * aplicar cualquier filtro de propiedad/autorización ANTES de calcular candidatos
 * (misma exigencia que la variante RAG/CAG del proyecto: nunca "traer todo y
 * filtrar después").
 */
interface MpiPatientRepository
{
    /**
     * Devuelve candidatos ya puntuados (0..1) para las demográficas dadas.
     *
     * @return array<int, MpiCandidate>
     *
     * @throws \App\Application\Patients\Exceptions\CentralUnavailableException si no se puede
     *         contactar la base CENTRAL (red caída, timeout, conexión rechazada).
     */
    public function findCandidates(PatientDemographics $demographics): array;

    /**
     * Crea una identidad nueva en el MPI y devuelve su global_id (uuid).
     *
     * @throws \App\Application\Patients\Exceptions\CentralUnavailableException
     */
    public function create(PatientDemographics $demographics): string;
}
