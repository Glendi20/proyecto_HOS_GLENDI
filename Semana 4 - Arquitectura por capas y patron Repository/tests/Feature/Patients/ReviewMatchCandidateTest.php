<?php

namespace Tests\Feature\Patients;

use App\Application\Patients\Ports\IdentityMatchCandidateRepository;
use App\Application\Patients\Ports\MpiPatientRepository;
use App\Application\Patients\Ports\PatientHospitalLinkRepository;
use App\Domain\Patients\Mpi\MpiCandidate;
use App\Infrastructure\Patients\Fakes\InMemoryIdentityMatchCandidateRepository;
use App\Infrastructure\Patients\Fakes\InMemoryMpiPatientRepository;
use App\Infrastructure\Patients\Fakes\InMemoryPatientHospitalLinkRepository;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithJwtApi;
use Tests\TestCase;

/**
 * Regla central: una coincidencia MPI ambigua nunca se resuelve sola. Estas
 * pruebas cubren la revisión humana explícita (confirmar/rechazar) y su
 * inmutabilidad una vez resuelta.
 */
class ReviewMatchCandidateTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithJwtApi;

    private InMemoryMpiPatientRepository $mpi;

    private InMemoryIdentityMatchCandidateRepository $matchCandidates;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->mpi = new InMemoryMpiPatientRepository();
        $this->matchCandidates = new InMemoryIdentityMatchCandidateRepository();

        $this->app->instance(MpiPatientRepository::class, $this->mpi);
        $this->app->instance(PatientHospitalLinkRepository::class, new InMemoryPatientHospitalLinkRepository());
        $this->app->instance(IdentityMatchCandidateRepository::class, $this->matchCandidates);
    }

    private function receptionistHeaders(Tenant $tenant): array
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('Recepcionista');

        return $this->apiHeadersFor($user, $tenant);
    }

    /**
     * Da de alta un paciente cuya coincidencia MPI queda ambigua y devuelve
     * [tenant, headers, matchCandidateId, mpiCandidateIdA].
     */
    private function registerAmbiguousPatient(): array
    {
        $tenant = Tenant::factory()->create();
        $headers = $this->receptionistHeaders($tenant);

        $this->mpi->seedCandidatesFor([
            new MpiCandidate('mpi-a', 0.70, ['birth_date']),
            new MpiCandidate('mpi-b', 0.68, ['birth_date']),
        ]);

        $response = $this->withHeaders($headers)->postJson('/api/v1/patients', [
            'first_name' => 'Rosa',
            'last_name' => 'Jiménez',
            'birth_date' => '1970-04-04',
            'gender' => 'F',
            'dpi' => null,
        ]);

        $response->assertCreated();

        return [$tenant, $headers, $response->json('match_candidate_id')];
    }

    public function test_confirmar_un_candidato_vincula_al_paciente_y_asigna_global_id(): void
    {
        [$tenant, $headers, $matchCandidateId] = $this->registerAmbiguousPatient();

        $response = $this->withHeaders($headers)->postJson(
            "/api/v1/patients/match-candidates/{$matchCandidateId}/resolve",
            ['decision' => 'confirm', 'mpi_patient_id' => 'mpi-a']
        );

        $response->assertOk();
        $response->assertJsonPath('global_id', 'mpi-a');

        $this->assertDatabaseHas('patients', [
            'tenant_id' => $tenant->id,
            'global_id' => 'mpi-a',
            'mpi_link_status' => 'auto_linked',
        ]);
    }

    public function test_rechazar_todos_los_candidatos_crea_una_identidad_nueva(): void
    {
        [, $headers, $matchCandidateId] = $this->registerAmbiguousPatient();

        $response = $this->withHeaders($headers)->postJson(
            "/api/v1/patients/match-candidates/{$matchCandidateId}/resolve",
            ['decision' => 'reject']
        );

        $response->assertOk();
        $this->assertNotContains($response->json('global_id'), ['mpi-a', 'mpi-b']);
    }

    public function test_una_coincidencia_ya_resuelta_no_puede_reprocesarse(): void
    {
        [, $headers, $matchCandidateId] = $this->registerAmbiguousPatient();

        $this->withHeaders($headers)->postJson(
            "/api/v1/patients/match-candidates/{$matchCandidateId}/resolve",
            ['decision' => 'confirm', 'mpi_patient_id' => 'mpi-a']
        )->assertOk();

        $second = $this->withHeaders($headers)->postJson(
            "/api/v1/patients/match-candidates/{$matchCandidateId}/resolve",
            ['decision' => 'confirm', 'mpi_patient_id' => 'mpi-b']
        );

        $second->assertStatus(409);
    }

    public function test_confirmar_un_mpi_patient_id_fuera_de_los_candidatos_es_rechazado(): void
    {
        [, $headers, $matchCandidateId] = $this->registerAmbiguousPatient();

        $response = $this->withHeaders($headers)->postJson(
            "/api/v1/patients/match-candidates/{$matchCandidateId}/resolve",
            ['decision' => 'confirm', 'mpi_patient_id' => 'mpi-inventado']
        );

        $response->assertStatus(422);
    }
}
