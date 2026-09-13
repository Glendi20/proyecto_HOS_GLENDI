<?php

namespace App\Infrastructure\Patients\Eloquent;

use Illuminate\Database\Eloquent\Model;

class PatientHospitalLinkModel extends Model
{
    protected $connection = 'central';

    protected $table = 'patient_hospital_links';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'local_patient_uuid',
        'mpi_patient_id',
        'status',
        'linked_by',
        'linked_at',
    ];

    protected $casts = [
        'linked_at' => 'datetime',
    ];
}
