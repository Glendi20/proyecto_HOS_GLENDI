<?php

namespace App\Infrastructure\Patients\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent sobre la conexión CENTRAL. No debe usarse desde fuera de
 * Infrastructure/Patients: el resto de la aplicación conoce solo los puertos
 * (App\Application\Patients\Ports\MpiPatientRepository).
 */
class MpiPatientModel extends Model
{
    protected $connection = 'central';

    protected $table = 'mpi_patients';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'full_name_normalized',
        'birth_date',
        'gender',
        'dpi_normalized',
        'status',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];
}
