<?php

namespace Database\Seeders;

use App\Domain\Patients\ValueObjects\PatientUuid;
use App\Infrastructure\Patients\Eloquent\IdentityMatchCandidateModel;
use App\Infrastructure\Patients\Eloquent\MpiPatientModel;
use App\Infrastructure\Patients\Eloquent\PatientHospitalLinkModel;
use App\Models\Patient;
use App\Models\Tenant;
use Database\Factories\MpiPatientFactory;
use Illuminate\Database\Seeder;

/**
 * Datos ficticios de demostración para el módulo 03 (paciente local + MPI CENTRAL).
 * Pensado para EVIDENCIA.md: deja un caso auto_linked y uno manual_review listos
 * para probar por API sin tener que dar de alta pacientes a mano.
 *
 * No usa nombres, DPI ni datos de personas reales (todo generado con Faker).
 */
class Mod03PatientsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'san-marcos-demo'],
            ['id' => '00000000-0000-4000-8000-000000000001', 'name' => 'Hospital General San Marcos (demo)', 'data' => []]
        );

        // Caso 1: paciente local ya vinculado automáticamente a una identidad MPI.
        $mpiLinked = (new MpiPatientFactory())->create([
            'full_name_normalized' => 'maria lopez perez',
            'birth_date' => '1990-05-14',
            'dpi_normalized' => '1234567890101',
        ]);

        $linkedPatient = Patient::query()->create([
            'tenant_id' => $tenant->id,
            // Se asigna explícitamente: DatabaseSeeder usa WithoutModelEvents, que
            // suprimiría el hook `creating` de App\Models\Patient que genera el uuid.
            'uuid' => PatientUuid::generate()->value(),
            'code' => 'PAC-9001',
            'first_name' => 'Maria',
            'last_name' => 'Lopez Perez',
            'birth_date' => '1990-05-14',
            'gender' => 'F',
            'dpi' => '1234567890101',
            'mpi_link_status' => 'auto_linked',
            'global_id' => $mpiLinked->id,
            'mpi_synced_at' => now(),
        ]);

        PatientHospitalLinkModel::query()->create([
            'id' => PatientUuid::generate()->value(),
            'tenant_id' => $tenant->id,
            'local_patient_uuid' => $linkedPatient->uuid,
            'mpi_patient_id' => $mpiLinked->id,
            'status' => 'auto_linked',
            'linked_by' => null,
            'linked_at' => now(),
        ]);

        // Caso 2: paciente local con coincidencia MPI ambigua, pendiente de revisión manual.
        $candidateA = (new MpiPatientFactory())->create([
            'full_name_normalized' => 'carlos hernandez ruiz',
            'birth_date' => '1985-02-20',
        ]);
        $candidateB = (new MpiPatientFactory())->create([
            'full_name_normalized' => 'carlos hernandez ruiz',
            'birth_date' => '1985-02-20',
        ]);

        $ambiguousPatient = Patient::query()->create([
            'tenant_id' => $tenant->id,
            'uuid' => PatientUuid::generate()->value(),
            'code' => 'PAC-9002',
            'first_name' => 'Carlos',
            'last_name' => 'Hernandez Ruiz',
            'birth_date' => '1985-02-20',
            'gender' => 'M',
            'dpi' => null,
            'mpi_link_status' => 'manual_review',
        ]);

        IdentityMatchCandidateModel::query()->create([
            'id' => PatientUuid::generate()->value(),
            'tenant_id' => $tenant->id,
            'local_patient_uuid' => $ambiguousPatient->uuid,
            'candidates' => [
                ['mpi_patient_id' => $candidateA->id, 'score' => 0.65, 'matched_fields' => ['birth_date', 'full_name']],
                ['mpi_patient_id' => $candidateB->id, 'score' => 0.65, 'matched_fields' => ['birth_date', 'full_name']],
            ],
            'reason' => 'Coincidencia ambigua: dos identidades MPI con el mismo nombre y fecha de nacimiento.',
            'status' => 'pending',
        ]);

        $this->command?->info('Mod03PatientsDemoSeeder: 2 pacientes demo (1 auto_linked, 1 manual_review).');
    }
}
