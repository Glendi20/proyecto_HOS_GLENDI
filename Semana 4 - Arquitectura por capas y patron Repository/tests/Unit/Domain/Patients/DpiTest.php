<?php

namespace Tests\Unit\Domain\Patients;

use App\Domain\Patients\ValueObjects\Dpi;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DpiTest extends TestCase
{
    public function test_nulo_o_vacio_se_considera_ausente(): void
    {
        $this->assertFalse(Dpi::fromNullable(null)->isPresent());
        $this->assertFalse(Dpi::fromNullable('')->isPresent());
        $this->assertFalse(Dpi::fromNullable('   ')->isPresent());
    }

    public function test_normaliza_separadores_y_conserva_13_digitos(): void
    {
        $dpi = Dpi::fromNullable('1234 56789 0101');

        $this->assertSame('1234567890101', $dpi->value());
    }

    public function test_rechaza_dpi_con_longitud_invalida(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Dpi::fromNullable('12345');
    }
}
