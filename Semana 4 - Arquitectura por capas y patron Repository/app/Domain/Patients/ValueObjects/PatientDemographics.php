<?php

namespace App\Domain\Patients\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Datos demográficos mínimos usados tanto para registrar un paciente local
 * como para buscar candidatos de emparejamiento en el MPI de CENTRAL.
 *
 * Es un Value Object inmutable: dos instancias con los mismos valores son
 * intercambiables. No depende de Eloquent ni de ningún framework.
 */
final class PatientDemographics
{
    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly DateTimeImmutable $birthDate,
        public readonly string $gender,
        public readonly Dpi $dpi,
    ) {
        if (trim($firstName) === '' || trim($lastName) === '') {
            throw new InvalidArgumentException('Nombre y apellido son obligatorios.');
        }

        if (! in_array($gender, ['M', 'F', 'otro'], true)) {
            throw new InvalidArgumentException('Género inválido.');
        }

        if ($birthDate > new DateTimeImmutable('today')) {
            throw new InvalidArgumentException('La fecha de nacimiento no puede ser futura.');
        }
    }

    /**
     * Nombre normalizado para comparación (minúsculas, sin espacios extra, sin acentos básicos).
     */
    public function normalizedFullName(): string
    {
        $full = mb_strtolower(trim($this->firstName.' '.$this->lastName));
        $full = preg_replace('/\s+/', ' ', $full) ?? $full;

        $unwanted = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n'];

        return strtr($full, $unwanted);
    }
}
