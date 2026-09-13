<?php

namespace App\Domain\Patients\ValueObjects;

use InvalidArgumentException;

/**
 * Identificador lógico (UUID) de un paciente local dentro de su HOSPITAL.
 *
 * Es la única forma permitida de referenciar un local_patient desde CENTRAL
 * (patient_hospital_links.local_patient_uuid): nunca una FK remota ni el id
 * autoincremental interno de la tabla `patients`.
 *
 * Deliberadamente no depende de ningún helper de framework (ni Illuminate\Support\Str)
 * para mantener el dominio libre de infraestructura: genera y valida UUID v4 con PHP puro.
 */
final class PatientUuid
{
    private const PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    private function __construct(private readonly string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException("«{$value}» no es un UUID v4 válido.");
        }
    }

    public static function generate(): self
    {
        $bytes = random_bytes(16);

        // Fija la versión (4) y variante (RFC 4122) según el estándar UUID v4.
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        $hex = bin2hex($bytes);

        $uuid = sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );

        return new self($uuid);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return strcasecmp($this->value, $other->value) === 0;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
