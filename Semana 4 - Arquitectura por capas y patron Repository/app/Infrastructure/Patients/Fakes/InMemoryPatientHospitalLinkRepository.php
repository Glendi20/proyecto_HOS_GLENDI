<?php

namespace App\Infrastructure\Patients\Fakes;

use App\Application\Patients\Ports\PatientHospitalLinkRepository;

final class InMemoryPatientHospitalLinkRepository implements PatientHospitalLinkRepository
{
    /** @var array<string, array{mpi_patient_id: string, status: string}> keyed by "tenant:uuid" */
    private array $links = [];

    public function link(
        string $tenantId,
        string $localPatientUuid,
        string $mpiPatientId,
        string $status,
        ?string $linkedBy
    ): void {
        $this->links["{$tenantId}:{$localPatientUuid}"] = [
            'mpi_patient_id' => $mpiPatientId,
            'status' => $status,
        ];
    }

    public function findActiveGlobalId(string $tenantId, string $localPatientUuid): ?string
    {
        $link = $this->links["{$tenantId}:{$localPatientUuid}"] ?? null;

        return ($link !== null && $link['status'] !== 'revoked') ? $link['mpi_patient_id'] : null;
    }
}
