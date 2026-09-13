<?php

namespace Tests\Feature\Patients;

use App\Application\Patients\Ports\IdentityMatchCandidateRepository;
use App\Application\Patients\Ports\MpiPatientRepository;
use App\Application\Patients\Ports\PatientHospitalLinkRepository;
use App\Application\Patients\Ports\PatientSyncOutboxRepository;
use App\Domain\Patients\Mpi\MpiCandidate;
use App\Infrastructure\Patients\Fakes\InMemoryIdentityMatchCandidateRepository;
use App\Infrastructure\Patients\Fakes\InMemoryMpiPatientRepository;
use App\Infrastructure\Patients\Fakes\InMemoryPatientHospitalLinkRepository;
use App\Infrastructure\Patients\Fakes\InMemoryPatientSyncOutboxRepository;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithJwtApi;
use Tests\TestCase;

/**
 * Pruebas de feature del CU "Registrar paciente local". El paciente se persiste
 * con el adaptador Eloquent/PostgreSQL real (vía RefreshDatabase); CENTRAL se
 * sustituye por los dobles InMemory para poder controlar cada escenario de forma
 * determinista, cumpliendo "el adaptador y el doble de prueba respetan el mismo
 * contrato observable" (criterio de aceptación del ADR).
 */
class RegisterLocalPatientTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithJwtApi;

    private InMemoryMpiPatientRepository $mpi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->mpi = new InMemoryMpiPatientRepository();

        $this->app->instance(MpiPatientRepository::class, $this->mpi);
        $this->app->instance(PatientHospitalLinkRepository::class, new InMemoryPatientHospitalLinkRepository());
        $this->app->instance(IdentityMatchCandidateRepository::class, new InMemoryIdentityMatchCandidateRepository());
    }

    private function receptionistHeaders(Tenant $tenant): array
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('Recepcionista');

        return $this->apiHeadersFor($user, $tenant);
    }

    public function test_alta_online_sin_coincidencias_crea_identidad_nueva_en_mpi(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->withHeaders($this->receptionistHeaders($tenant))->postJson('/api/v1/patients', [
            'first_name' => 'Ana',
            'last_name' => 'Ramírez',
            'birth_date' => '1995-06-01',
            'gender' => 'F',
            'dpi' => '1111111111111',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('mpi_link_status', 'auto_linked');
        $response->assertJsonPath('central_available', true);
        $this->assertNotNull($response->json('global_id'));

        $this->assertDatabaseHas('patients', [
            'tenant_id' => $tenant->id,
            'dpi' => '1111111111111',
            'mpi_link_status' => 'auto_linked',
        ]);
    }

    public function test_alta_online_con_candidato_dominante_hace_auto_link(): void
    {
        $tenant = Tenant::factory()->create();

        $this->mpi->seedCandidatesFor([
            new MpiCandidate('mpi-existing-1', 0.96, ['dpi', 'birth_date', 'full_name']),
        ]);

        $response = $this->withHeaders($this->receptionistHeaders($tenant))->postJson('/api/v1/patients', [
            'first_name' => 'Carlos',
            'last_name' => 'Méndez',
            'birth_date' => '1980-01-01',
            'gender' => 'M',
            'dpi' => '2222222222222',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('mpi_link_status', 'auto_linked');
        $response->assertJsonPath('global_id', 'mpi-existing-1');
    }

    public function test_alta_online_con_coincidencia_ambigua_queda_pendiente_de_revision_manual(): void
    {
        $tenant = Tenant::factory()->create();

        $this->mpi->seedCandidatesFor([
            new MpiCandidate('mpi-a', 0.70, ['birth_date']),
            new MpiCandidate('mpi-b', 0.68, ['birth_date']),
        ]);

        $response = $this->withHeaders($this->receptionistHeaders($tenant))->postJson('/api/v1/patients', [
            'first_name' => 'Diego',
            'last_name' => 'Solís',
            'birth_date' => '1975-09-09',
            'gender' => 'M',
            'dpi' => null,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('mpi_link_status', 'manual_review');
        $this->assertNull($response->json('global_id'));
        $this->assertNotNull($response->json('match_candidate_id'));
    }

    public function test_rechaza_dpi_duplicado_dentro_del_mismo_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $headers = $this->receptionistHeaders($tenant);

        Patient::factory()->create(['tenant_id' => $tenant->id, 'dpi' => '3333333333333']);

        $response = $this->withHeaders($headers)->postJson('/api/v1/patients', [
            'first_name' => 'Otra',
            'last_name' => 'Persona',
            'birth_date' => '2000-01-01',
            'gender' => 'F',
            'dpi' => '3333333333333',
        ]);

        $response->assertStatus(409);
    }

    public function test_continua_localmente_si_central_no_esta_disponible_y_encola_evento_en_outbox(): void
    {
        $tenant = Tenant::factory()->create();
        $this->mpi->simulateOutage(true);

        $response = $this->withHeaders($this->receptionistHeaders($tenant))->postJson('/api/v1/patients', [
            'first_name' => 'Elena',
            'last_name' => 'Torres',
            'birth_date' => '1992-12-12',
            'gender' => 'F',
            'dpi' => '4444444444444',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('mpi_link_status', 'pending');
        $response->assertJsonPath('central_available', false);
        $this->assertNull($response->json('global_id'));

        // El alta local existe igual, y queda un evento en el outbox para reintentar.
        $this->assertDatabaseHas('patients', [
            'tenant_id' => $tenant->id,
            'dpi' => '4444444444444',
            'mpi_link_status' => 'pending',
        ]);
        $this->assertDatabaseCount('patient_sync_outbox', 1);
    }

    public function test_un_rol_de_solo_lectura_no_puede_registrar_pacientes(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('Médico');

        $response = $this->withHeaders($this->apiHeadersFor($user, $tenant))->postJson('/api/v1/patients', [
            'first_name' => 'X',
            'last_name' => 'Y',
            'birth_date' => '1990-01-01',
            'gender' => 'M',
            'dpi' => null,
        ]);

        $response->assertStatus(403);
    }
}
