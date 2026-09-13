<?php

namespace App\Domain\Patients\Exceptions;

use DomainException;

final class MatchCandidateNotFoundException extends DomainException
{
    public static function forId(string $id): self
    {
        return new self("No existe una coincidencia MPI pendiente con id {$id}.");
    }
}
