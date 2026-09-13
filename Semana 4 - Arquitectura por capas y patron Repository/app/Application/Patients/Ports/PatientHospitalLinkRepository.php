<?php

namespace App\Application\Patients\Ports;

/**
 * Puerto hacia patient_hospital_link (CENTRAL): el vínculo lógico entre un
 * local_patient de un hospital y un mpi_patient del MPI. UUID lógicos en ambos
 * extremos; nunca una FK remota hacia la base HOSPITAL.
 */
interface PatientHospitalLinkRepository
{
    /**
     * @throws \App\Application\Patients\Exceptions\CentralUnavailableException
     */
    public function link(
        string $tenantId,
        string $localPatientUuid,
        string $mpiPatientId,
        string $status,
        ?string $linkedBy
    ): void;

    /**
     * @throws \App\Application\Patients\Exceptions\CentralUnavailableException
     */
    public function findActiveGlobalId(string $tenantId, string $localPatientUuid): ?string;
}
