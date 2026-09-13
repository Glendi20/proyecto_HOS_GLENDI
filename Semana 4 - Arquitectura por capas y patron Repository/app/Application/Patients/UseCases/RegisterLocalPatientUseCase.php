<?php

namespace App\Application\Patients\UseCases;

use App\Application\Patients\DTO\RegisterPatientInput;
use App\Application\Patients\DTO\RegisterPatientResult;
use App\Application\Patients\Exceptions\CentralUnavailableException;
use App\Application\Patients\Ports\IdentityMatchCandidateRepository;
use App\Application\Patients\Ports\LocalPatientRepository;
use App\Application\Patients\Ports\MpiPatientRepository;
use App\Application\Patients\Ports\PatientHospitalLinkRepository;
use App\Application\Patients\Ports\PatientSyncOutboxRepository;
use App\Domain\Patients\Exceptions\DuplicateDpiException;
use App\Domain\Patients\LocalPatient;
use App\Domain\Patients\Mpi\PatientMatchingPolicy;
use App\Domain\Patients\ValueObjects\Dpi;
use App\Domain\Patients\ValueObjects\PatientDemographics;
use DateTimeImmutable;

/**
 * CU: Registrar paciente local + intento de vínculo con el MPI de CENTRAL.
 *
 * Regla central obligatoria (ADR-001): el alta LOCAL siempre se confirma, incluso
 * si CENTRAL o la red fallan (continuidad offline). El intento de sincronización
 * con el MPI es "best effort": si CENTRAL responde, se aplica la política de
 * emparejamiento (auto_link / manual_review / no_match -> nueva identidad); si
 * CENTRAL no responde, el paciente local queda con mpi_link_status=pending y se
 * encola un evento en el outbox para reintentar después (idempotente por event_id).
 */
final class RegisterLocalPatientUseCase
{
    public function __construct(
        private readonly LocalPatientRepository $localPatients,
        private readonly MpiPatientRepository $mpiPatients,
        private readonly PatientHospitalLinkRepository $links,
        private readonly IdentityMatchCandidateRepository $matchCandidates,
        private readonly PatientSyncOutboxRepository $outbox,
        private readonly PatientMatchingPolicy $matchingPolicy,
    ) {
    }

    /**
     * @throws DuplicateDpiException
     */
    public function handle(RegisterPatientInput $input): RegisterPatientResult
    {
        $dpi = Dpi::fromNullable($input->dpi);

        if ($dpi->isPresent()) {
            $existing = $this->localPatients->findByDpi($input->tenantId, (string) $dpi->value());

            if ($existing !== null) {
                throw DuplicateDpiException::forDpi((string) $dpi->value());
            }
        }

        $demographics = new PatientDemographics(
            firstName: $input->firstName,
            lastName: $input->lastName,
            birthDate: new DateTimeImmutable($input->birthDate),
            gender: $input->gender,
            dpi: $dpi,
        );

        $code = $this->localPatients->nextCode($input->tenantId);
        $patient = LocalPatient::register($input->tenantId, $code, $demographics);

        // 1) El alta local se confirma primero y siempre, sin depender de CENTRAL.
        $this->localPatients->save($patient);

        $matchCandidateId = null;

        try {
            $candidates = $this->mpiPatients->findCandidates($demographics);
            $decision = $this->matchingPolicy->decide($candidates);

            if ($decision->isNoMatch()) {
                $globalId = $this->mpiPatients->create($demographics);
                $this->links->link($input->tenantId, $patient->uuid()->value(), $globalId, 'auto_linked', null);
                $patient->markAutoLinked($globalId);
            } elseif ($decision->isAutoLink()) {
                $globalId = $decision->winner->mpiPatientId;
                $this->links->link($input->tenantId, $patient->uuid()->value(), $globalId, 'auto_linked', null);
                $patient->markAutoLinked($globalId);
            } else {
                $matchCandidateId = $this->matchCandidates->createPending(
                    $input->tenantId,
                    $patient->uuid()->value(),
                    $decision
                );
                $patient->markPendingManualReview();
            }

            $this->localPatients->save($patient);

            return new RegisterPatientResult(
                uuid: $patient->uuid()->value(),
                code: $patient->code(),
                mpiLinkStatus: $patient->mpiLinkStatus(),
                globalId: $patient->globalId(),
                matchCandidateId: $matchCandidateId,
                centralWasAvailable: true,
            );
        } catch (CentralUnavailableException) {
            // 2) CENTRAL/la red fallaron: el alta local YA quedó confirmada arriba.
            //    Se encola el evento para sincronizar después, de forma idempotente.
            $patient->markSyncPending();
            $this->localPatients->save($patient);

            $this->outbox->enqueue(
                eventId: $this->newEventId(),
                tenantId: $input->tenantId,
                localPatientUuid: $patient->uuid()->value(),
                aggregateVersion: 1,
                eventType: 'patient_registered',
                payload: [
                    'first_name' => $demographics->firstName,
                    'last_name' => $demographics->lastName,
                    'birth_date' => $input->birthDate,
                    'gender' => $demographics->gender,
                    'dpi' => $dpi->value(),
                ]
            );

            return new RegisterPatientResult(
                uuid: $patient->uuid()->value(),
                code: $patient->code(),
                mpiLinkStatus: $patient->mpiLinkStatus(),
                globalId: null,
                matchCandidateId: null,
                centralWasAvailable: false,
            );
        }
    }

    private function newEventId(): string
    {
        return \App\Domain\Patients\ValueObjects\PatientUuid::generate()->value();
    }
}
