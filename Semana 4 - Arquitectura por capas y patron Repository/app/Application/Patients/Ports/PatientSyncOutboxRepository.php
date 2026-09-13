<?php

namespace App\Application\Patients\Ports;

/**
 * Puerto del outbox HOSPITAL -> CENTRAL. Vive en la base HOSPITAL (el escritor
 * original) para garantizar que un alta local nunca se pierda ni bloquee aunque
 * CENTRAL esté caído: se encola el evento y se reintenta después, de forma
 * idempotente (event_id único).
 */
interface PatientSyncOutboxRepository
{
    public function enqueue(
        string $eventId,
        string $tenantId,
        string $localPatientUuid,
        int $aggregateVersion,
        string $eventType,
        array $payload
    ): void;

    /**
     * @return array<int, array{event_id: string, tenant_id: string, local_patient_uuid: string,
     *               aggregate_version: int, event_type: string, payload: array, attempts: int}>
     */
    public function pending(int $limit = 50): array;

    public function markSent(string $eventId): void;

    public function markFailed(string $eventId): void;
}
