<?php

namespace App\Infrastructure\Patients\Fakes;

use App\Application\Patients\Ports\LocalPatientRepository;
use App\Domain\Patients\LocalPatient;

/**
 * Doble de prueba (Fake/InMemory) del puerto LocalPatientRepository. Respeta el
 * mismo contrato observable que EloquentLocalPatientRepository, sin tocar
 * PostgreSQL: útil para pruebas de aplicación rápidas y deterministas.
 */
final class InMemoryLocalPatientRepository implements LocalPatientRepository
{
    /** @var array<string, LocalPatient> keyed by uuid */
    private array $byUuid = [];

    /** @var array<string, int> next correlativo por tenant */
    private array $sequence = [];

    public function save(LocalPatient $patient): void
    {
        $this->byUuid[$patient->uuid()->value()] = $patient;
    }

    public function findByUuid(string $tenantId, string $uuid): ?LocalPatient
    {
        $patient = $this->byUuid[$uuid] ?? null;

        return ($patient !== null && $patient->tenantId() === $tenantId) ? $patient : null;
    }

    public function findByDpi(string $tenantId, string $dpi): ?LocalPatient
    {
        foreach ($this->byUuid as $patient) {
            if ($patient->tenantId() === $tenantId && $patient->demographics()->dpi->value() === $dpi) {
                return $patient;
            }
        }

        return null;
    }

    public function search(string $tenantId, string $term, int $page, int $perPage): array
    {
        $term = mb_strtolower($term);

        $matches = array_values(array_filter(
            $this->byUuid,
            function (LocalPatient $p) use ($tenantId, $term): bool {
                if ($p->tenantId() !== $tenantId) {
                    return false;
                }

                if ($term === '') {
                    return true;
                }

                $haystack = mb_strtolower(
                    $p->demographics()->firstName.' '.$p->demographics()->lastName.' '.
                    ($p->demographics()->dpi->value() ?? '').' '.$p->code()
                );

                return str_contains($haystack, $term);
            }
        ));

        $total = count($matches);
        $offset = ($page - 1) * $perPage;

        return [
            'data' => array_slice($matches, $offset, $perPage),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function nextCode(string $tenantId): string
    {
        $this->sequence[$tenantId] = ($this->sequence[$tenantId] ?? 0) + 1;

        return sprintf('PAC-%04d', $this->sequence[$tenantId]);
    }
}
