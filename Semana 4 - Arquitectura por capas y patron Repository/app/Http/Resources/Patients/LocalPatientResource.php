<?php

namespace App\Http\Resources\Patients;

use App\Domain\Patients\LocalPatient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LocalPatient
 */
class LocalPatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var LocalPatient $patient */
        $patient = $this->resource;

        return [
            'uuid' => $patient->uuid()->value(),
            'code' => $patient->code(),
            'first_name' => $patient->demographics()->firstName,
            'last_name' => $patient->demographics()->lastName,
            'birth_date' => $patient->demographics()->birthDate->format('Y-m-d'),
            'gender' => $patient->demographics()->gender,
            'dpi' => $patient->demographics()->dpi->value(),
            'mpi_link_status' => $patient->mpiLinkStatus(),
            'global_id' => $patient->globalId(),
        ];
    }
}
