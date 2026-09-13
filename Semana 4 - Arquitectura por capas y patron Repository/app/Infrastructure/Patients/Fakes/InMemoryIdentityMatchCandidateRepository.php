<?php

namespace App\Infrastructure\Patients\Fakes;

use App\Application\Patients\Ports\IdentityMatchCandidateRepository;
use App\Domain\Patients\Mpi\MatchDecision;
use App\Domain\Patients\ValueObjects\PatientUuid;

final class InMemoryIdentityMatchCandidateRepository implements IdentityMatchCandidateRepository
{
    /** @var array<string, array{id: string, tenant_id: string, local_patient_uuid: string, status: string, candidates: array}> */
    private array $rows = [];

    public function createPending(string $tenantId, string $localPatientUuid, MatchDecision $decision): string
    {
        $id = PatientUuid::generate()->value();

        $this->rows[$id] = [
            'id' => $id,
            'tenant_id' => $tenantId,
            'local_patient_uuid' => $localPatientUuid,
            'status' => 'pending',
            'candidates' => array_map(
                static fn ($c): array => ['mpi_patient_id' => $c->mpiPatientId, 'score' => $c->score],
                $decision->candidates
            ),
        ];

        return $id;
    }

    public function find(string $id): ?array
    {
        return $this->rows[$id] ?? null;
    }

    public function pendingForTenant(string $tenantId): array
    {
        return array_values(array_filter(
            $this->rows,
            fn (array $r): bool => $r['tenant_id'] === $tenantId && $r['status'] === 'pending'
        ));
    }

    public function markResolved(string $id, string $status, ?string $reviewedBy): void
    {
        if (isset($this->rows[$id])) {
            $this->rows[$id]['status'] = $status;
        }
    }
}
