<?php

namespace App\Infrastructure\Patients\Eloquent;

use App\Application\Patients\Exceptions\CentralUnavailableException;
use App\Application\Patients\Ports\MpiPatientRepository;
use App\Domain\Patients\Mpi\MpiCandidate;
use App\Domain\Patients\ValueObjects\PatientDemographics;
use Illuminate\Database\QueryException;
use PDOException;

/**
 * Adaptador PostgreSQL/Eloquent hacia el MPI de CENTRAL.
 *
 * El "top-k" se calcula sobre una preselección (shortlist) filtrada por DPI o fecha
 * de nacimiento en SQL (usa idx_mpi_patients_dpi_birth); el puntaje fino de cada
 * candidato de la shortlist se calcula en PHP con una heurística determinística
 * documentada en ADR-001 (no requiere pgvector: no es la variante RAG/CAG).
 */
class EloquentMpiPatientRepository implements MpiPatientRepository
{
    private const SHORTLIST_LIMIT = 50;

    public function findCandidates(PatientDemographics $demographics): array
    {
        try {
            $shortlist = MpiPatientModel::query()
                ->where('status', 'active')
                ->where(function ($query) use ($demographics) {
                    if ($demographics->dpi->value() !== null) {
                        $query->orWhere('dpi_normalized', $demographics->dpi->value());
                    }

                    $query->orWhere('birth_date', $demographics->birthDate->format('Y-m-d'));
                })
                ->limit(self::SHORTLIST_LIMIT)
                ->get();
        } catch (QueryException|PDOException $e) {
            throw CentralUnavailableException::fromPrevious($e);
        }

        return $shortlist
            ->map(fn (MpiPatientModel $candidate): MpiCandidate => $this->score($candidate, $demographics))
            ->all();
    }

    public function create(PatientDemographics $demographics): string
    {
        $id = \App\Domain\Patients\ValueObjects\PatientUuid::generate()->value();

        try {
            MpiPatientModel::query()->create([
                'id' => $id,
                'full_name_normalized' => $demographics->normalizedFullName(),
                'birth_date' => $demographics->birthDate->format('Y-m-d'),
                'gender' => $demographics->gender,
                'dpi_normalized' => $demographics->dpi->value(),
                'status' => 'active',
            ]);
        } catch (QueryException|PDOException $e) {
            throw CentralUnavailableException::fromPrevious($e);
        }

        return $id;
    }

    private function score(MpiPatientModel $candidate, PatientDemographics $demographics): MpiCandidate
    {
        $score = 0.0;
        $matched = [];

        if ($candidate->dpi_normalized !== null
            && $demographics->dpi->value() !== null
            && $candidate->dpi_normalized === $demographics->dpi->value()) {
            $score += 0.6;
            $matched[] = 'dpi';
        }

        if ($candidate->birth_date->format('Y-m-d') === $demographics->birthDate->format('Y-m-d')) {
            $score += 0.25;
            $matched[] = 'birth_date';
        }

        similar_text($candidate->full_name_normalized, $demographics->normalizedFullName(), $percent);

        if ($percent >= 90.0) {
            $matched[] = 'full_name';
        }

        $score += ($percent / 100) * 0.4;

        return new MpiCandidate(
            mpiPatientId: $candidate->id,
            score: min(1.0, round($score, 4)),
            matchedFields: $matched,
        );
    }
}
