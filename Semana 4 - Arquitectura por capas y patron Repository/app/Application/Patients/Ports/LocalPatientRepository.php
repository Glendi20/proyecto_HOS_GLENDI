<?php

namespace App\Application\Patients\Ports;

use App\Domain\Patients\LocalPatient;
use App\Domain\Patients\ValueObjects\PatientUuid;

/**
 * Puerto (Repository) orientado a las necesidades del caso de uso, no un CRUD genérico.
 * El adaptador HOSPITAL (Eloquent/PostgreSQL) y el doble de prueba (InMemory) implementan
 * este mismo contrato observable.
 */
interface LocalPatientRepository
{
    /**
     * Persiste un alta o actualización de paciente local. Debe cumplirse siempre
     * dentro del hospital (tenant) del propio paciente, sin depender de CENTRAL.
     */
    public function save(LocalPatient $patient): void;

    public function findByUuid(string $tenantId, string $uuid): ?LocalPatient;

    public function findByDpi(string $tenantId, string $dpi): ?LocalPatient;

    /**
     * Búsqueda por nombre, DPI o código, acotada estrictamente al tenant recibido
     * (nunca a uno indicado por el cliente fuera de su contexto autenticado).
     *
     * @return array{data: array<int, LocalPatient>, total: int, page: int, per_page: int}
     */
    public function search(string $tenantId, string $term, int $page, int $perPage): array;

    public function nextCode(string $tenantId): string;
}
