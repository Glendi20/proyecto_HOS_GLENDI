<?php

namespace App\Infrastructure\Patients\Eloquent;

use Illuminate\Database\Eloquent\Model;

class IdentityMatchCandidateModel extends Model
{
    protected $connection = 'central';

    protected $table = 'identity_match_candidates';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'local_patient_uuid',
        'candidates',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'candidates' => 'array',
        'reviewed_at' => 'datetime',
    ];
}
