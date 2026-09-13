<?php

namespace App\Application\Patients\Ports;

use App\Domain\Patients\Mpi\MatchDecision;

/**
 * Puerto hacia identity_match_candidate (CENTRAL): registra coincidencias MPI
 * ambiguas que requieren revisión humana. Nunca se resuelve automáticamente.
 */
interface IdentityMatchCandidateRepository
{
    /**
     * @return string id (uuid) de la coincidencia creada, pendiente de revisión.
     *
     * @throws \App\Application\Patients\Exceptions\CentralUnavailableException
     */
    public function createPending(string $tenantId, string $localPatientUuid, MatchDecision $decision): string;

    /**
     * @return array{id: string, tenant_id: string, local_patient_uuid: string, status: string,
     *               candidates: array<int, array{mpi_patient_id: string, score: float}>}|null
     */
    public function find(string $id): ?array;

    /**
     * @return array<int, array{id: string, tenant_id: string, local_patient_uuid: string, status: string}>
     */
    public function pendingForTenant(string $tenantId): array;

    public function markResolved(string $id, string $status, ?string $reviewedBy): void;
}
