<?php

namespace App\Domain\Patients\ValueObjects;

use InvalidArgumentException;

/**
 * DPI (Documento Personal de Identificación) guatemalteco.
 *
 * Value Object de dominio: valida formato y expone una representación normalizada,
 * pero NO decide unicidad (eso es responsabilidad del repositorio/caso de uso, que
 * conoce el alcance del tenant/hospital).
 */
final class Dpi
{
    private readonly ?string $value;

    private function __construct(?string $value)
    {
        $this->value = $value;
    }

    public static function fromNullable(?string $raw): self
    {
        if ($raw === null || trim($raw) === '') {
            return new self(null);
        }

        $normalized = preg_replace('/\D/', '', $raw) ?? '';

        if (strlen($normalized) !== 13) {
            throw new InvalidArgumentException(
                'El DPI debe tener 13 dígitos numéricos (formato CUI de Guatemala).'
            );
        }

        return new self($normalized);
    }

    public function isPresent(): bool
    {
        return $this->value !== null;
    }

    public function value(): ?string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
