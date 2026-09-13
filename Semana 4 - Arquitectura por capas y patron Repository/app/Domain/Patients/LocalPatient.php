<?php

namespace App\Domain\Patients;

use App\Domain\Patients\ValueObjects\PatientDemographics;
use App\Domain\Patients\ValueObjects\PatientUuid;

/**
 * Entidad de dominio: paciente registrado en el HOSPITAL local.
 *
 * Es la autoridad clínica local (ver ADR-001): existe y puede seguir escribiéndose
 * aunque CENTRAL o la red no estén disponibles. El vínculo con el MPI de CENTRAL
 * (globalId) es un dato adicional que se resuelve de forma asíncrona/best-effort,
 * nunca una condición para que el registro local exista.
 */
final class LocalPatient
{
    public const LINK_PENDING = 'pending';

    public const LINK_AUTO_LINKED = 'auto_linked';

    public const LINK_MANUAL_REVIEW = 'manual_review';

    public const LINK_UNLINKED = 'unlinked';

    private function __construct(
        private readonly PatientUuid $uuid,
        private readonly string $tenantId,
        private readonly string $code,
        private readonly PatientDemographics $demographics,
        private string $mpiLinkStatus,
        private ?string $globalId,
    ) {
    }

    /**
     * $code debe venir ya asignado por el repositorio (LocalPatientRepository::nextCode)
     * ANTES de construir la entidad: la generación del correlativo por tenant es una
     * responsabilidad de infraestructura (requiere ver el estado persistido), pero una vez
     * asignado es un dato inmutable del agregado.
     */
    public static function register(string $tenantId, string $code, PatientDemographics $demographics): self
    {
        return new self(
            uuid: PatientUuid::generate(),
            tenantId: $tenantId,
            code: $code,
            demographics: $demographics,
            mpiLinkStatus: self::LINK_PENDING,
            globalId: null,
        );
    }

    public static function reconstitute(
        PatientUuid $uuid,
        string $tenantId,
        string $code,
        PatientDemographics $demographics,
        string $mpiLinkStatus,
        ?string $globalId
    ): self {
        return new self($uuid, $tenantId, $code, $demographics, $mpiLinkStatus, $globalId);
    }

    public function uuid(): PatientUuid
    {
        return $this->uuid;
    }

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function demographics(): PatientDemographics
    {
        return $this->demographics;
    }

    public function mpiLinkStatus(): string
    {
        return $this->mpiLinkStatus;
    }

    public function globalId(): ?string
    {
        return $this->globalId;
    }

    /**
     * Se aplica cuando la política de emparejamiento decide un vínculo automático
     * de alta confianza con un único candidato del MPI.
     */
    public function markAutoLinked(string $globalId): void
    {
        $this->mpiLinkStatus = self::LINK_AUTO_LINKED;
        $this->globalId = $globalId;
    }

    /**
     * Se aplica cuando la coincidencia es ambigua: el paciente local sigue existiendo
     * y operando con normalidad, solo que su vínculo con el MPI queda pendiente de
     * revisión humana (identity_match_candidate en CENTRAL). Nunca se fusiona
     * silenciosamente ni se mueve el expediente fuera del hospital fuente.
     */
    public function markPendingManualReview(): void
    {
        $this->mpiLinkStatus = self::LINK_MANUAL_REVIEW;
    }

    /**
     * Se aplica cuando CENTRAL no está disponible: el registro local ya existe y
     * continúa operando; el vínculo con el MPI queda pendiente de sincronización
     * posterior (outbox).
     */
    public function markSyncPending(): void
    {
        $this->mpiLinkStatus = self::LINK_PENDING;
    }

    /**
     * Resultado de una revisión manual que confirma un candidato específico.
     */
    public function confirmManualLink(string $globalId): void
    {
        $this->mpiLinkStatus = self::LINK_AUTO_LINKED;
        $this->globalId = $globalId;
    }
}
