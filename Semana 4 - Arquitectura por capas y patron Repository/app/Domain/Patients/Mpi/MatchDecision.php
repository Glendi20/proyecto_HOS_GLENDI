<?php

namespace App\Domain\Patients\Mpi;

/**
 * Resultado inmutable de aplicar PatientMatchingPolicy. Representa exactamente una
 * de tres decisiones posibles; nunca una fusión automática ni un movimiento de datos
 * fuera del hospital fuente (regla central del módulo, ver ESPECIFICACION.md).
 */
final class MatchDecision
{
    public const NO_MATCH = 'no_match';

    public const AUTO_LINK = 'auto_link';

    public const MANUAL_REVIEW = 'manual_review';

    /**
     * @param  array<int, MpiCandidate>  $candidates  todos los candidatos considerados (auditoría)
     */
    private function __construct(
        public readonly string $type,
        public readonly array $candidates,
        public readonly ?MpiCandidate $winner,
        public readonly string $reason,
    ) {
    }

    public static function noMatch(array $candidates, string $reason): self
    {
        return new self(self::NO_MATCH, $candidates, null, $reason);
    }

    public static function autoLink(MpiCandidate $winner, array $candidates, string $reason): self
    {
        return new self(self::AUTO_LINK, $candidates, $winner, $reason);
    }

    public static function manualReview(array $candidates, string $reason): self
    {
        return new self(self::MANUAL_REVIEW, $candidates, null, $reason);
    }

    public function isNoMatch(): bool
    {
        return $this->type === self::NO_MATCH;
    }

    public function isAutoLink(): bool
    {
        return $this->type === self::AUTO_LINK;
    }

    public function isManualReview(): bool
    {
        return $this->type === self::MANUAL_REVIEW;
    }
}
