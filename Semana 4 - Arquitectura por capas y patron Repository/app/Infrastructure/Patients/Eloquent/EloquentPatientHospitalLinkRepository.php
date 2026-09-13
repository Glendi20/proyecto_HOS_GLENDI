<?php

namespace App\Infrastructure\Patients\Eloquent;

use App\Application\Patients\Exceptions\CentralUnavailableException;
use App\Application\Patients\Ports\PatientHospitalLinkRepository;
use App\Domain\Patients\ValueObjects\PatientUuid;
use Illuminate\Database\QueryException;
use PDOException;

class EloquentPatientHospitalLinkRepository implements PatientHospitalLinkRepository
{
    public function link(
        string $tenantId,
        string $localPatientUuid,
        string $mpiPatientId,
        string $status,
        ?string $linkedBy
    ): void {
        try {
            // Revoca cualquier vínculo activo previo para este paciente local antes de
            // crear el nuevo: conserva el historial (nunca se sobrescribe una fila).
            PatientHospitalLinkModel::query()
                ->where('tenant_id', $tenantId)
                ->where('local_patient_uuid', $localPatientUuid)
                ->where('status', '!=', 'revoked')
                ->update(['status' => 'revoked']);

            PatientHospitalLinkModel::query()->create([
                'id' => PatientUuid::generate()->value(),
                'tenant_id' => $tenantId,
                'local_patient_uuid' => $localPatientUuid,
                'mpi_patient_id' => $mpiPatientId,
                'status' => $status,
                'linked_by' => $linkedBy,
                'linked_at' => now(),
            ]);
        } catch (QueryException|PDOException $e) {
            throw CentralUnavailableException::fromPrevious($e);
        }
    }

    public function findActiveGlobalId(string $tenantId, string $localPatientUuid): ?string
    {
        try {
            return PatientHospitalLinkModel::query()
                ->where('tenant_id', $tenantId)
                ->where('local_patient_uuid', $localPatientUuid)
                ->where('status', '!=', 'revoked')
                ->latest('linked_at')
                ->value('mpi_patient_id');
        } catch (QueryException|PDOException $e) {
            throw CentralUnavailableException::fromPrevious($e);
        }
    }
}
