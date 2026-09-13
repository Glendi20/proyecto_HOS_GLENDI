<?php

namespace Tests\Unit\Domain\Patients;

use App\Domain\Patients\Mpi\MpiCandidate;
use App\Domain\Patients\Mpi\PatientMatchingPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas de la regla central del módulo: la política de emparejamiento MPI.
 * Puras (sin base de datos, sin framework): PHPUnit\Framework\TestCase directo.
 */
class PatientMatchingPolicyTest extends TestCase
{
    private PatientMatchingPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new PatientMatchingPolicy(autoLinkThreshold: 0.92, reviewThreshold: 0.60);
    }

    public function test_sin_candidatos_decide_no_match(): void
    {
        $decision = $this->policy->decide([]);

        $this->assertTrue($decision->isNoMatch());
        $this->assertNull($decision->winner);
    }

    public function test_un_candidato_dominante_de_alta_confianza_decide_auto_link(): void
    {
        $winner = new MpiCandidate('mpi-1', 0.97, ['dpi', 'birth_date', 'full_name']);

        $decision = $this->policy->decide([$winner]);

        $this->assertTrue($decision->isAutoLink());
        $this->assertSame('mpi-1', $decision->winner->mpiPatientId);
    }

    public function test_candidato_unico_por_debajo_del_umbral_de_revision_decide_no_match(): void
    {
        $decision = $this->policy->decide([new MpiCandidate('mpi-1', 0.40, [])]);

        $this->assertTrue($decision->isNoMatch());
    }

    public function test_dos_candidatos_con_score_cercano_decide_manual_review(): void
    {
        $a = new MpiCandidate('mpi-1', 0.80, ['birth_date', 'full_name']);
        $b = new MpiCandidate('mpi-2', 0.78, ['birth_date', 'full_name']);

        $decision = $this->policy->decide([$a, $b]);

        $this->assertTrue($decision->isManualReview());
        $this->assertCount(2, $decision->candidates);
    }

    public function test_candidato_con_score_alto_pero_sin_margen_frente_al_segundo_decide_manual_review(): void
    {
        // Ambos por encima del umbral de auto-link, pero demasiado cerca entre sí: ambiguo.
        $a = new MpiCandidate('mpi-1', 0.95, ['full_name']);
        $b = new MpiCandidate('mpi-2', 0.93, ['full_name']);

        $decision = $this->policy->decide([$a, $b]);

        $this->assertTrue($decision->isManualReview());
    }

    public function test_nunca_fusiona_automaticamente_ante_ambiguedad_el_ganador_es_null(): void
    {
        $a = new MpiCandidate('mpi-1', 0.70, []);
        $b = new MpiCandidate('mpi-2', 0.65, []);

        $decision = $this->policy->decide([$a, $b]);

        $this->assertTrue($decision->isManualReview());
        $this->assertNull($decision->winner);
    }
}
