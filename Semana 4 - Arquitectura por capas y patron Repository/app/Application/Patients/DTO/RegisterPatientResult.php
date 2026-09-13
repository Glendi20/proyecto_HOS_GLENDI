<?php

namespace App\Application\Patients\DTO;

final class RegisterPatientResult
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $code,
        public readonly string $mpiLinkStatus,
        public readonly ?string $globalId,
        public readonly ?string $matchCandidateId,
        public readonly bool $centralWasAvailable = true,
    ) {
    }
}
