<?php

namespace Tests\Unit\Domain\Patients;

use App\Domain\Patients\ValueObjects\Dpi;
use App\Domain\Patients\ValueObjects\PatientDemographics;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PatientDemographicsTest extends TestCase
{
    public function test_rechaza_fecha_de_nacimiento_futura(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PatientDemographics(
            firstName: 'Ana',
            lastName: 'Pérez',
            birthDate: new DateTimeImmutable('+1 day'),
            gender: 'F',
            dpi: Dpi::fromNullable(null),
        );
    }

    public function test_rechaza_genero_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PatientDemographics(
            firstName: 'Ana',
            lastName: 'Pérez',
            birthDate: new DateTimeImmutable('1990-01-01'),
            gender: 'X',
            dpi: Dpi::fromNullable(null),
        );
    }

    public function test_normaliza_nombre_completo_sin_acentos_ni_espacios_dobles(): void
    {
        $demographics = new PatientDemographics(
            firstName: 'María  José',
            lastName: 'Núñez',
            birthDate: new DateTimeImmutable('1990-01-01'),
            gender: 'F',
            dpi: Dpi::fromNullable(null),
        );

        $this->assertSame('maria jose nunez', $demographics->normalizedFullName());
    }
}
