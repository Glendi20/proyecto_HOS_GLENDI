<?php

namespace App\Infrastructure\Patients\Eloquent;

use App\Application\Patients\Ports\PatientSyncOutboxRepository;

/**
 * Vive en la base HOSPITAL: nunca lanza CentralUnavailableException porque el
 * outbox es, precisamente, el mecanismo para no depender de que CENTRAL responda.
 */
class EloquentPatientSyncOutboxRepository implements PatientSyncOutboxRepository
{
    public function enqueue(
        string $eventId,
        string $tenantId,
        string $localPatientUuid,
        int $aggregateVersion,
        string $eventType,
        array $payload
    ): void {
        // event_id es la PK: un reintento con el mismo id no duplica el evento (idempotencia).
        PatientSyncOutboxModel::query()->firstOrCreate(
            ['event_id' => $eventId],
            [
                'tenant_id' => $tenantId,
                'local_patient_uuid' => $localPatientUuid,
                'aggregate_version' => $aggregateVersion,
                'event_type' => $eventType,
                'payload' => $payload,
                'status' => 'pending',
                'attempts' => 0,
            ]
        );
    }

    public function pending(int $limit = 50): array
    {
        return PatientSyncOutboxModel::query()
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (PatientSyncOutboxModel $row): array => [
                'event_id' => $row->event_id,
                'tenant_id' => $row->tenant_id,
                'local_patient_uuid' => $row->local_patient_uuid,
                'aggregate_version' => $row->aggregate_version,
                'event_type' => $row->event_type,
                'payload' => $row->payload,
                'attempts' => $row->attempts,
            ])
            ->all();
    }

    public function markSent(string $eventId): void
    {
        PatientSyncOutboxModel::query()->whereKey($eventId)->update([
            'status' => 'sent',
            'processed_at' => now(),
        ]);
    }

    public function markFailed(string $eventId): void
    {
        PatientSyncOutboxModel::query()->whereKey($eventId)->increment('attempts', 1, [
            'status' => 'pending',
        ]);
    }
}
