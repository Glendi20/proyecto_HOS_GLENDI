<?php

namespace App\Application\Patients\UseCases;

use App\Application\Patients\Ports\LocalPatientRepository;

/**
 * CU: Buscar pacientes locales por nombre, DPI o código.
 *
 * La búsqueda es puramente HOSPITAL: no consulta CENTRAL ni el MPI. El tenant
 * viene siempre del contexto autenticado (nunca de un parámetro del cliente),
 * por lo que es imposible mezclar hospitales en un mismo resultado.
 */
final class SearchLocalPatientsUseCase
{
    public function __construct(private readonly LocalPatientRepository $localPatients)
    {
    }

    /**
     * @return array{data: array<int, \App\Domain\Patients\LocalPatient>, total: int, page: int, per_page: int}
     */
    public function handle(string $tenantId, string $term, int $page = 1, int $perPage = 15): array
    {
        return $this->localPatients->search($tenantId, trim($term), max(1, $page), max(1, min(100, $perPage)));
    }
}
