<?php

namespace App\Infrastructure\Patients\Eloquent;

use App\Application\Patients\Exceptions\CentralUnavailableException;
use App\Application\Patients\Ports\IdentityMatchCandidateRepository;
use App\Domain\Patients\Mpi\MatchDecision;
use App\Domain\Patients\ValueObjects\PatientUuid;
use Illuminate\Database\QueryException;
use PDOException;

class EloquentIdentityMatchCandidateRepository implements IdentityMatchCandidateRepository
{
    public function createPending(string $tenantId, string $localPatientUuid, MatchDecision $decision): string
    {
        $id = PatientUuid::generate()->value();

        try {
            IdentityMatchCandidateModel::query()->create([
                'id' => $id,
                'tenant_id' => $tenantId,
                'local_patient_uuid' => $localPatientUuid,
                'candidates' => array_map(
                    static fn ($c): array => [
                        'mpi_patient_id' => $c->mpiPatientId,
                        'score' => $c->score,
                        'matched_fields' => $c->matchedFields,
                    ],
                    $decision->candidates
                ),
                'reason' => $decision->reason,
                'status' => 'pending',
            ]);
        } catch (QueryException|PDOException $e) {
            throw CentralUnavailableException::fromPrevious($e);
        }

        return $id;
    }

    public function find(string $id): ?array
    {
        $row = IdentityMatchCandidateModel::query()->find($id);

        if ($row === null) {
            return null;
        }

        return [
            'id' => $row->id,
            'tenant_id' => $row->tenant_id,
            'local_patient_uuid' => $row->local_patient_uuid,
            'status' => $row->status,
            'candidates' => $row->candidates,
        ];
    }

    public function pendingForTenant(string $tenantId): array
    {
        return IdentityMatchCandidateModel::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get(['id', 'tenant_id', 'local_patient_uuid', 'status'])
            ->map(fn (IdentityMatchCandidateModel $row): array => [
                'id' => $row->id,
                'tenant_id' => $row->tenant_id,
                'local_patient_uuid' => $row->local_patient_uuid,
                'status' => $row->status,
            ])
            ->all();
    }

    public function markResolved(string $id, string $status, ?string $reviewedBy): void
    {
        IdentityMatchCandidateModel::query()->whereKey($id)->update([
            'status' => $status,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
        ]);
    }
}
