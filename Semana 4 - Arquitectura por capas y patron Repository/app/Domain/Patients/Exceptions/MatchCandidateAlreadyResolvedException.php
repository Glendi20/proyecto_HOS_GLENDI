<?php

namespace App\Domain\Patients\Exceptions;

use DomainException;

/**
 * Una identity_match_candidate ya revisada (confirmed/rejected) es inmutable:
 * no se puede volver a resolver. Cualquier corrección posterior debe modelarse
 * como una nueva revisión/evento auditado, nunca sobrescribiendo la decisión previa.
 */
final class MatchCandidateAlreadyResolvedException extends DomainException
{
    public static function forId(string $id, string $status): self
    {
        return new self("La coincidencia MPI {$id} ya fue resuelta (estado: {$status}) y no puede reprocesarse.");
    }
}
