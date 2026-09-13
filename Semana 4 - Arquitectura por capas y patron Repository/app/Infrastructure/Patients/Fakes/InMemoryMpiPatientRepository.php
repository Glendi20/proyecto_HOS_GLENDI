<?php

namespace App\Infrastructure\Patients\Fakes;

use App\Application\Patients\Exceptions\CentralUnavailableException;
use App\Application\Patients\Ports\MpiPatientRepository;
use App\Domain\Patients\Mpi\MpiCandidate;
use App\Domain\Patients\ValueObjects\PatientDemographics;
use App\Domain\Patients\ValueObjects\PatientUuid;

/**
 * Doble de prueba de CENTRAL/MPI. Permite:
 *  - precargar candidatos fijos para un escenario concreto (seedCandidatesFor), y
 *  - simular que CENTRAL/la red no responde (simulateOutage), para probar la
 *    regla de continuidad offline del HOSPITAL sin infraestructura real.
 */
final class InMemoryMpiPatientRepository implements MpiPatientRepository
{
    private bool $outage = false;

    /** @var array<int, MpiCandidate> */
    private array $fixedCandidates = [];

    /** @var array<string, PatientDemographics> */
    private array $created = [];

    public function simulateOutage(bool $outage = true): void
    {
        $this->outage = $outage;
    }

    /**
     * @param  array<int, MpiCandidate>  $candidates
     */
    public function seedCandidatesFor(array $candidates): void
    {
        $this->fixedCandidates = $candidates;
    }

    public function findCandidates(PatientDemographics $demographics): array
    {
        $this->guardAvailable();

        return $this->fixedCandidates;
    }

    public function create(PatientDemographics $demographics): string
    {
        $this->guardAvailable();

        $id = PatientUuid::generate()->value();
        $this->created[$id] = $demographics;

        return $id;
    }

    private function guardAvailable(): void
    {
        if ($this->outage) {
            throw CentralUnavailableException::fromPrevious(new \RuntimeException('CENTRAL simulada caída en prueba.'));
        }
    }
}
