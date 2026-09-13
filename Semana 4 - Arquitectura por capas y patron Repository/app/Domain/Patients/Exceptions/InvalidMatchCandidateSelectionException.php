<?php

namespace App\Domain\Patients\Exceptions;

use DomainException;

/**
 * El mpi_patient_id elegido al confirmar una revisión no está entre los
 * candidatos que originaron la identity_match_candidate: nunca se acepta un
 * vínculo hacia una identidad que no fue parte de la coincidencia ambigua.
 */
final class InvalidMatchCandidateSelectionException extends DomainException
{
    public static function forChoice(string $chosenId): self
    {
        return new self("«{$chosenId}» no está entre los candidatos registrados para esta revisión.");
    }
}
