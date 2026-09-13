<?php

namespace App\Domain\Patients\Mpi;

/**
 * Regla central obligatoria del módulo 03 (ver ESPECIFICACION.md / ADR-001):
 *
 * "El permiso y el límite CENTRAL/HOSPITAL se aplican antes de la búsqueda vectorial/MPI.
 * Una coincidencia MPI ambigua crea identity_match_candidate y nunca fusiona
 * silenciosamente ni mueve el expediente fuera del hospital fuente."
 *
 * Esta clase es pura: no toca base de datos, no depende de Eloquent ni de HTTP.
 * Recibe candidatos ya filtrados/puntuados por la infraestructura (adaptador de
 * CENTRAL) y únicamente decide qué hacer con ellos.
 *
 * Reglas:
 *  - Sin candidatos               -> NO_MATCH (se crea un mpi_patient nuevo y se auto-vincula).
 *  - Un único candidato dominante -> AUTO_LINK (score >= autoLinkThreshold y con margen
 *                                    suficiente frente al segundo mejor candidato).
 *  - Cualquier otro caso con al menos un candidato por encima del umbral de revisión
 *    (empates, scores intermedios, más de un candidato fuerte) -> MANUAL_REVIEW.
 */
final class PatientMatchingPolicy
{
    /**
     * Margen mínimo que debe sacarle el mejor candidato al segundo mejor para
     * considerarse "dominante" y no ambiguo, aun estando ambos por encima del umbral.
     */
    private const DOMINANCE_MARGIN = 0.05;

    public function __construct(
        private readonly float $autoLinkThreshold = 0.92,
        private readonly float $reviewThreshold = 0.60,
    ) {
    }

    /**
     * @param  array<int, MpiCandidate>  $candidates  candidatos ya puntuados por CENTRAL,
     *                                                 ordenados o no (esta clase los ordena).
     */
    public function decide(array $candidates): MatchDecision
    {
        if ($candidates === []) {
            return MatchDecision::noMatch([], 'Sin candidatos en el MPI: se registra como identidad nueva.');
        }

        $sorted = $candidates;
        usort($sorted, static fn (MpiCandidate $a, MpiCandidate $b): int => $b->score <=> $a->score);

        $best = $sorted[0];
        $second = $sorted[1] ?? null;

        if ($best->score < $this->reviewThreshold) {
            return MatchDecision::noMatch(
                $sorted,
                sprintf('Mejor candidato (%.2f) por debajo del umbral de revisión (%.2f).', $best->score, $this->reviewThreshold)
            );
        }

        $isDominant = $second === null || ($best->score - $second->score) >= self::DOMINANCE_MARGIN;

        if ($best->score >= $this->autoLinkThreshold && $isDominant) {
            return MatchDecision::autoLink(
                $best,
                $sorted,
                sprintf('Candidato único dominante con score %.2f (>= %.2f).', $best->score, $this->autoLinkThreshold)
            );
        }

        return MatchDecision::manualReview(
            $sorted,
            sprintf(
                'Coincidencia ambigua: mejor score %.2f, %d candidato(s) por encima del umbral de revisión (%.2f).',
                $best->score,
                count(array_filter($sorted, fn (MpiCandidate $c): bool => $c->score >= $this->reviewThreshold)),
                $this->reviewThreshold
            )
        );
    }
}
