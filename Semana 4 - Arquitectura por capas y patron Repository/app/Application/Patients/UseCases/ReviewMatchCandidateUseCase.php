<?php

namespace App\Application\Patients\UseCases;

use App\Application\Patients\Ports\IdentityMatchCandidateRepository;
use App\Application\Patients\Ports\LocalPatientRepository;
use App\Application\Patients\Ports\MpiPatientRepository;
use App\Application\Patients\Ports\PatientHospitalLinkRepository;
use App\Domain\Patients\Exceptions\InvalidMatchCandidateSelectionException;
use App\Domain\Patients\Exceptions\LocalPatientNotFoundException;
use App\Domain\Patients\Exceptions\MatchCandidateAlreadyResolvedException;
use App\Domain\Patients\Exceptions\MatchCandidateNotFoundException;

/**
 * CU: Revisión humana de una coincidencia MPI ambigua (identity_match_candidate).
 *
 * Regla central: nunca se fusiona automáticamente. Un humano autorizado decide
 * explícitamente entre:
 *  - confirmar un mpi_patient_id específico de los candidatos (vínculo confirmado), o
 *  - rechazar todos los candidatos (se crea una identidad nueva en el MPI).
 *
 * La decisión queda auditada (reviewed_by, reviewed_at) y es inmutable una vez tomada.
 */
final class ReviewMatchCandidateUseCase
{
    public function __construct(
        private readonly IdentityMatchCandidateRepository $matchCandidates,
        private readonly LocalPatientRepository $localPatients,
        private readonly PatientHospitalLinkRepository $links,
        private readonly MpiPatientRepository $mpiPatients,
    ) {
    }

    /**
     * @throws MatchCandidateNotFoundException
     * @throws MatchCandidateAlreadyResolvedException
     * @throws LocalPatientNotFoundException
     */
    public function confirm(string $matchCandidateId, string $chosenMpiPatientId, string $reviewedBy): string
    {
        $candidate = $this->requirePending($matchCandidateId);

        $validIds = array_map(
            static fn (array $c): string => $c['mpi_patient_id'],
            $candidate['candidates']
        );

        if (! in_array($chosenMpiPatientId, $validIds, true)) {
            throw InvalidMatchCandidateSelectionException::forChoice($chosenMpiPatientId);
        }

        $patient = $this->localPatients->findByUuid($candidate['tenant_id'], $candidate['local_patient_uuid']);

        if ($patient === null) {
            throw LocalPatientNotFoundException::forUuid($candidate['local_patient_uuid']);
        }

        $this->links->link(
            $candidate['tenant_id'],
            $candidate['local_patient_uuid'],
            $chosenMpiPatientId,
            'manual_confirmed',
            $reviewedBy
        );

        $patient->confirmManualLink($chosenMpiPatientId);
        $this->localPatients->save($patient);

        $this->matchCandidates->markResolved($matchCandidateId, 'confirmed', $reviewedBy);

        return $chosenMpiPatientId;
    }

    /**
     * @throws MatchCandidateNotFoundException
     * @throws MatchCandidateAlreadyResolvedException
     * @throws LocalPatientNotFoundException
     */
    public function reject(string $matchCandidateId, string $reviewedBy): string
    {
        $candidate = $this->requirePending($matchCandidateId);

        $patient = $this->localPatients->findByUuid($candidate['tenant_id'], $candidate['local_patient_uuid']);

        if ($patient === null) {
            throw LocalPatientNotFoundException::forUuid($candidate['local_patient_uuid']);
        }

        // Ningún candidato existente correspondía: se crea una identidad nueva en el MPI.
        $globalId = $this->mpiPatients->create($patient->demographics());

        $this->links->link(
            $candidate['tenant_id'],
            $candidate['local_patient_uuid'],
            $globalId,
            'manual_confirmed',
            $reviewedBy
        );

        $patient->confirmManualLink($globalId);
        $this->localPatients->save($patient);

        $this->matchCandidates->markResolved($matchCandidateId, 'rejected', $reviewedBy);

        return $globalId;
    }

    /**
     * @return array{id: string, tenant_id: string, local_patient_uuid: string, status: string,
     *               candidates: array<int, array{mpi_patient_id: string, score: float}>}
     */
    private function requirePending(string $matchCandidateId): array
    {
        $candidate = $this->matchCandidates->find($matchCandidateId);

        if ($candidate === null) {
            throw MatchCandidateNotFoundException::forId($matchCandidateId);
        }

        if ($candidate['status'] !== 'pending') {
            throw MatchCandidateAlreadyResolvedException::forId($matchCandidateId, $candidate['status']);
        }

        return $candidate;
    }
}
