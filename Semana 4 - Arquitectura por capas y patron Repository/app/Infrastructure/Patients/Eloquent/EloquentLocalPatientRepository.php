<?php

namespace App\Infrastructure\Patients\Eloquent;

use App\Application\Patients\Ports\LocalPatientRepository;
use App\Domain\Patients\LocalPatient;
use App\Domain\Patients\ValueObjects\Dpi;
use App\Domain\Patients\ValueObjects\PatientDemographics;
use App\Domain\Patients\ValueObjects\PatientUuid;
use App\Models\Patient as PatientModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Adaptador PostgreSQL/Eloquent del puerto LocalPatientRepository. Opera siempre
 * sobre la conexión por defecto (HOSPITAL), acotado por tenant_id.
 */
class EloquentLocalPatientRepository implements LocalPatientRepository
{
    public function save(LocalPatient $patient): void
    {
        PatientModel::query()->updateOrCreate(
            ['uuid' => $patient->uuid()->value()],
            [
                'tenant_id' => $patient->tenantId(),
                'code' => $patient->code(),
                'first_name' => $patient->demographics()->firstName,
                'last_name' => $patient->demographics()->lastName,
                'birth_date' => $patient->demographics()->birthDate->format('Y-m-d'),
                'gender' => $patient->demographics()->gender,
                'dpi' => $patient->demographics()->dpi->value(),
                'mpi_link_status' => $patient->mpiLinkStatus(),
                'global_id' => $patient->globalId(),
                'mpi_synced_at' => $patient->mpiLinkStatus() === LocalPatient::LINK_AUTO_LINKED
                    ? now()
                    : null,
            ]
        );
    }

    public function findByUuid(string $tenantId, string $uuid): ?LocalPatient
    {
        $row = PatientModel::query()
            ->where('tenant_id', $tenantId)
            ->where('uuid', $uuid)
            ->first();

        return $row === null ? null : $this->toDomain($row);
    }

    public function findByDpi(string $tenantId, string $dpi): ?LocalPatient
    {
        $row = PatientModel::query()
            ->where('tenant_id', $tenantId)
            ->where('dpi', $dpi)
            ->first();

        return $row === null ? null : $this->toDomain($row);
    }

    public function search(string $tenantId, string $term, int $page, int $perPage): array
    {
        $query = PatientModel::query()->where('tenant_id', $tenantId);

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('dpi', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%");
            });
        }

        $paginator = $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => array_map(fn (PatientModel $row): LocalPatient => $this->toDomain($row), $paginator->items()),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
        ];
    }

    public function nextCode(string $tenantId): string
    {
        // Reintenta ante colisión de código concurrente (uq_patients_tenant_code),
        // ver database/migrations/2026_08_21_100050_scope_patients_code_uniqueness_to_tenant.php.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = DB::transaction(function () use ($tenantId) {
                $lastNumber = PatientModel::query()
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->orderByDesc('id')
                    ->value('code');

                $number = 1;

                if (is_string($lastNumber) && preg_match('/(\d+)$/', $lastNumber, $m) === 1) {
                    $number = ((int) $m[1]) + 1;
                }

                return sprintf('PAC-%04d', $number);
            });

            $exists = PatientModel::query()
                ->where('tenant_id', $tenantId)
                ->where('code', $candidate)
                ->exists();

            if (! $exists) {
                return $candidate;
            }
        }

        // Extremadamente improbable: fallback con sufijo aleatorio para no bloquear el alta.
        return 'PAC-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    private function toDomain(PatientModel $row): LocalPatient
    {
        return LocalPatient::reconstitute(
            PatientUuid::fromString($row->uuid),
            (string) $row->tenant_id,
            $row->code,
            new PatientDemographics(
                firstName: $row->first_name,
                lastName: $row->last_name,
                birthDate: new DateTimeImmutable($row->birth_date->format('Y-m-d')),
                gender: $row->gender === 'otro' ? 'otro' : $row->gender,
                dpi: Dpi::fromNullable($row->dpi),
            ),
            $row->mpi_link_status ?? LocalPatient::LINK_PENDING,
            $row->global_id,
        );
    }
}
