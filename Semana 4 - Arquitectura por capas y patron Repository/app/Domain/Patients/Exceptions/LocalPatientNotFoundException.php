<?php

namespace App\Domain\Patients\Exceptions;

use DomainException;

final class LocalPatientNotFoundException extends DomainException
{
    public static function forUuid(string $uuid): self
    {
        return new self("No existe un paciente local con uuid {$uuid} en este hospital.");
    }
}
