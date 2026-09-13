<?php

namespace App\Infrastructure\Patients\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Vive en la conexión por defecto (HOSPITAL): es el propio hospital quien escribe
 * el outbox al confirmar un alta local, sin depender de que CENTRAL esté arriba.
 */
class PatientSyncOutboxModel extends Model
{
    protected $table = 'patient_sync_outbox';

    protected $primaryKey = 'event_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'event_id',
        'tenant_id',
        'local_patient_uuid',
        'aggregate_version',
        'event_type',
        'payload',
        'status',
        'attempts',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
