<?php

namespace App\Application\Patients\UseCases;

use App\Application\Patients\Exceptions\CentralUnavailableException;
use App\Application\Patients\Ports\IdentityMatchCandidateRepository;
use App\Application\Patients\Ports\LocalPatientRepository;
use App\Application\Patients\Ports\MpiPatientRepository;
use App\Application\Patients\Ports\PatientHospitalLinkRepository;
use App\Application\Patients\Ports\PatientSyncOutboxRepository;
use App\Domain\Patients\Mpi\PatientMatchingPolicy;
use App\Domain\Patients\ValueObjects\Dpi;
use App\Domain\Patients\ValueObjects\PatientDemographics;
use DateTimeImmutable;

/**
 * Job/comando idempotente que drena el outbox HOSPITAL -> CENTRAL (patients:sync-mpi).
 *
 * Reintenta el vínculo MPI de los pacientes que quedaron con mpi_link_status=pending
 * porque CENTRAL no estaba disponible en el momento del alta. Es seguro ejecutarlo
 * repetidamente: cada evento tiene un event_id único y solo se marca `sent` cuando
 * el vínculo (o la revisión pendiente) quedó realmente creado en CENTRAL.
 */
final class SyncPendingPatientsUseCase
{
    public function __construct(
        private readonly PatientSyncOutboxRepository $outbox,
        private readonly LocalPatientRepository $localPatients,
        private readonly MpiPatientRepository $mpiPatients,
        private readonly PatientHospitalLinkRepository $links,
        private readonly IdentityMatchCandidateRepository $matchCandidates,
        private readonly PatientMatchingPolicy $matchingPolicy,
    ) {
    }

    /**
     * @return array{processed: int, linked: int, pending_review: int, still_unavailable: int}
     */
    public function handle(int $limit = 50): array
    {
        $stats = ['processed' => 0, 'linked' => 0, 'pending_review' => 0, 'still_unavailable' => 0];

        foreach ($this->outbox->pending($limit) as $event) {
            $stats['processed']++;

            $patient = $this->localPatients->findByUuid($event['tenant_id'], $event['local_patient_uuid']);

            if ($patient === null) {
                // El paciente local ya no existe; el evento no puede reprocesarse.
                $this->outbox->markSent($event['event_id']);

                continue;
            }

            $demographics = new PatientDemographics(
                firstName: $event['payload']['first_name'],
                lastName: $event['payload']['last_name'],
                birthDate: new DateTimeImmutable($event['payload']['birth_date']),
                gender: $event['payload']['gender'],
                dpi: Dpi::fromNullable($event['payload']['dpi'] ?? null),
            );

            try {
                $candidates = $this->mpiPatients->findCandidates($demographics);
                $decision = $this->matchingPolicy->decide($candidates);

                if ($decision->isNoMatch()) {
                    $globalId = $this->mpiPatients->create($demographics);
                    $this->links->link($event['tenant_id'], $event['local_patient_uuid'], $globalId, 'auto_linked', null);
                    $patient->markAutoLinked($globalId);
                    $stats['linked']++;
                } elseif ($decision->isAutoLink()) {
                    $globalId = $decision->winner->mpiPatientId;
                    $this->links->link($event['tenant_id'], $event['local_patient_uuid'], $globalId, 'auto_linked', null);
                    $patient->markAutoLinked($globalId);
                    $stats['linked']++;
                } else {
                    $this->matchCandidates->createPending($event['tenant_id'], $event['local_patient_uuid'], $decision);
                    $patient->markPendingManualReview();
                    $stats['pending_review']++;
                }

                $this->localPatients->save($patient);
                $this->outbox->markSent($event['event_id']);
            } catch (CentralUnavailableException) {
                $this->outbox->markFailed($event['event_id']);
                $stats['still_unavailable']++;
            }
        }

        return $stats;
    }
}
