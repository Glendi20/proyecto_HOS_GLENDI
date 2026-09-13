<?php

namespace App\Application\Patients\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Señala que CENTRAL (red o base de datos) no respondió. Los adaptadores de
 * Infrastructure deben capturar sus excepciones de bajo nivel (PDOException,
 * QueryException, timeouts HTTP, etc.) y relanzarlas como esta excepción de
 * Application, para que los casos de uso puedan decidir "continuar localmente"
 * sin acoplarse a detalles de PostgreSQL/Eloquent.
 */
final class CentralUnavailableException extends RuntimeException
{
    public static function fromPrevious(Throwable $previous): self
    {
        return new self('CENTRAL no está disponible: '.$previous->getMessage(), previous: $previous);
    }
}
