<?php

namespace App\Infrastructure\Patients\Fakes;

use App\Application\Patients\Ports\PatientSyncOutboxRepository;

final class InMemoryPatientSyncOutboxRepository implements PatientSyncOutboxRepository
{
    /** @var array<string, array{event_id: string, tenant_id: string, local_patient_uuid: string, aggregate_version: int, event_type: string, payload: array, attempts: int, status: string}> */
    private array $events = [];

    public function enqueue(
        string $eventId,
        string $tenantId,
        string $localPatientUuid,
        int $aggregateVersion,
        string $eventType,
        array $payload
    ): void {
        // Idempotente: un event_id repetido no duplica el evento encolado.
        if (isset($this->events[$eventId])) {
            return;
        }

        $this->events[$eventId] = [
            'event_id' => $eventId,
            'tenant_id' => $tenantId,
            'local_patient_uuid' => $localPatientUuid,
            'aggregate_version' => $aggregateVersion,
            'event_type' => $eventType,
            'payload' => $payload,
            'attempts' => 0,
            'status' => 'pending',
        ];
    }

    public function pending(int $limit = 50): array
    {
        $pending = array_values(array_filter($this->events, fn (array $e): bool => $e['status'] === 'pending'));

        return array_slice($pending, 0, $limit);
    }

    public function markSent(string $eventId): void
    {
        if (isset($this->events[$eventId])) {
            $this->events[$eventId]['status'] = 'sent';
        }
    }

    public function markFailed(string $eventId): void
    {
        if (isset($this->events[$eventId])) {
            $this->events[$eventId]['attempts']++;
        }
    }

    public function count(): int
    {
        return count($this->events);
    }
}
