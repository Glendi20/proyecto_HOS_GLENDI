<?php

namespace Tests\Feature\Patients;

use App\Infrastructure\Patients\Eloquent\PatientHospitalLinkModel;
use App\Models\Patient;
use App\Models\Tenant;
use Database\Factories\MpiPatientFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Prueba de integración PostgreSQL real (criterio de aceptación / apartado 10 del
 * enunciado). Se salta automáticamente si las conexiones activas no son pgsql,
 * porque phpunit.xml fija sqlite en memoria por defecto para el resto de la
 * suite. Para ejecutarla contra PostgreSQL real (el servicio ya corre en esta
 * máquina como "postgresql-x64-17"):
 *
 *   1. Crear las dos bases (una sola vez):
 *      psql -U postgres -c "CREATE DATABASE shi_hospital_glendi_test;"
 *      psql -U postgres -c "CREATE DATABASE shi_central_glendi_test;"
 *
 *   2. Ejecutar solo esta prueba forzando el driver pgsql vía variables de entorno
 *      del proceso (phpunit.xml no las sobreescribe: sus <env> solo aplican si la
 *      variable no viene ya definida desde fuera):
 *
 *      DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=shi_hospital_glendi_test \
 *      DB_USERNAME=postgres DB_PASSWORD=postgres \
 *      CENTRAL_DB_CONNECTION=pgsql CENTRAL_DB_HOST=127.0.0.1 CENTRAL_DB_PORT=5432 \
 *      CENTRAL_DB_DATABASE=shi_central_glendi_test CENTRAL_DB_USERNAME=postgres CENTRAL_DB_PASSWORD=postgres \
 *      php artisan test --filter=PostgresPatientIntegrationTest
 */
class PostgresPatientIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql' || DB::connection('central')->getDriverName() !== 'pgsql') {
            $this->markTestSkipped(
                'Requiere DB_CONNECTION=pgsql y CENTRAL_DB_CONNECTION=pgsql reales. Ver docstring de esta clase.'
            );
        }
    }

    public function test_migrate_fresh_deja_las_tablas_esperadas_en_cada_base(): void
    {
        $this->assertTrue(Schema::hasTable('patients'));
        $this->assertTrue(Schema::hasTable('patient_sync_outbox'));
        $this->assertTrue(Schema::connection('central')->hasTable('tenants'));
        $this->assertTrue(Schema::connection('central')->hasTable('mpi_patients'));
        $this->assertTrue(Schema::connection('central')->hasTable('patient_hospital_links'));
        $this->assertTrue(Schema::connection('central')->hasTable('identity_match_candidates'));

        // HOSPITAL y CENTRAL son bases físicamente distintas: una tabla exclusiva
        // de CENTRAL no debe existir del lado HOSPITAL, y viceversa.
        $this->assertFalse(Schema::hasTable('mpi_patients'));
        $this->assertFalse(Schema::connection('central')->hasTable('patients'));
    }

    public function test_postgres_rechaza_codigo_duplicado_dentro_del_mismo_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        Patient::factory()->create(['tenant_id' => $tenant->id, 'code' => 'PAC-0001']);

        $this->expectException(QueryException::class);

        DB::connection()->table('patients')->insert([
            'tenant_id' => $tenant->id,
            'uuid' => (string) Str::uuid(),
            'code' => 'PAC-0001',
            'first_name' => 'Duplicado',
            'last_name' => 'Test',
            'birth_date' => '2000-01-01',
            'gender' => 'M',
            'mpi_link_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_postgres_permite_un_unico_vinculo_activo_por_paciente_local_via_indice_parcial(): void
    {
        $tenant = Tenant::factory()->create();
        $localPatientUuid = (string) Str::uuid();

        $mpiA = (new MpiPatientFactory())->create();
        $mpiB = (new MpiPatientFactory())->create();

        PatientHospitalLinkModel::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'local_patient_uuid' => $localPatientUuid,
            'mpi_patient_id' => $mpiA->id,
            'status' => 'auto_linked',
            'linked_at' => now(),
        ]);

        // Un segundo vínculo ACTIVO para el mismo paciente local debe chocar con el
        // índice único parcial uq_links_active_per_local_patient (solo existe en pgsql).
        $this->expectException(QueryException::class);

        PatientHospitalLinkModel::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'local_patient_uuid' => $localPatientUuid,
            'mpi_patient_id' => $mpiB->id,
            'status' => 'auto_linked',
            'linked_at' => now(),
        ]);
    }

    public function test_revocar_el_vinculo_activo_permite_crear_uno_nuevo(): void
    {
        $tenant = Tenant::factory()->create();
        $localPatientUuid = (string) Str::uuid();
        $mpi = (new MpiPatientFactory())->create();

        PatientHospitalLinkModel::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'local_patient_uuid' => $localPatientUuid,
            'mpi_patient_id' => $mpi->id,
            'status' => 'revoked',
            'linked_at' => now(),
        ]);

        PatientHospitalLinkModel::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'local_patient_uuid' => $localPatientUuid,
            'mpi_patient_id' => $mpi->id,
            'status' => 'auto_linked',
            'linked_at' => now(),
        ]);

        $this->assertDatabaseCount('patient_hospital_links', 2, 'central');
    }
}
