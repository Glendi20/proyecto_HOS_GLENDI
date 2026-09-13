<?php

namespace Tests\Unit\Domain\Patients;

use App\Domain\Patients\LocalPatient;
use App\Domain\Patients\ValueObjects\Dpi;
use App\Domain\Patients\ValueObjects\PatientDemographics;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class LocalPatientTest extends TestCase
{
    private function demographics(): PatientDemographics
    {
        return new PatientDemographics(
            firstName: 'Luis',
            lastName: 'García',
            birthDate: new DateTimeImmutable('1988-03-10'),
            gender: 'M',
            dpi: Dpi::fromNullable(null),
        );
    }

    public function test_al_registrar_queda_pendiente_de_vinculo_mpi(): void
    {
        $patient = LocalPatient::register('tenant-1', 'PAC-0001', $this->demographics());

        $this->assertSame(LocalPatient::LINK_PENDING, $patient->mpiLinkStatus());
        $this->assertNull($patient->globalId());
        $this->assertSame('PAC-0001', $patient->code());
    }

    public function test_marcar_auto_vinculado_asigna_global_id(): void
    {
        $patient = LocalPatient::register('tenant-1', 'PAC-0001', $this->demographics());

        $patient->markAutoLinked('global-uuid-123');

        $this->assertSame(LocalPatient::LINK_AUTO_LINKED, $patient->mpiLinkStatus());
        $this->assertSame('global-uuid-123', $patient->globalId());
    }

    public function test_marcar_revision_manual_no_asigna_global_id(): void
    {
        $patient = LocalPatient::register('tenant-1', 'PAC-0001', $this->demographics());

        $patient->markPendingManualReview();

        $this->assertSame(LocalPatient::LINK_MANUAL_REVIEW, $patient->mpiLinkStatus());
        $this->assertNull($patient->globalId());
    }

    public function test_el_paciente_local_existe_y_es_valido_aunque_quede_pendiente_de_sincronizar(): void
    {
        // Regla central: el registro local sigue siendo un paciente válido y operable
        // aunque CENTRAL no haya respondido (continuidad offline).
        $patient = LocalPatient::register('tenant-1', 'PAC-0001', $this->demographics());

        $patient->markSyncPending();

        $this->assertSame(LocalPatient::LINK_PENDING, $patient->mpiLinkStatus());
        $this->assertSame('tenant-1', $patient->tenantId());
        $this->assertNotEmpty($patient->uuid()->value());
    }
}
