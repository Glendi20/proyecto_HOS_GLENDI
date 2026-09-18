<?php

namespace App\Http\Support\Patients;

use App\Domain\Patients\Exceptions\DuplicateDpiException;
use App\Domain\Patients\Exceptions\InvalidMatchCandidateSelectionException;
use App\Domain\Patients\Exceptions\LocalPatientNotFoundException;
use App\Domain\Patients\Exceptions\MatchCandidateAlreadyResolvedException;
use App\Domain\Patients\Exceptions\MatchCandidateNotFoundException;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Traduce las excepciones de dominio/aplicación del módulo Pacientes a una
 * respuesta HTTP uniforme, en un único lugar.
 *
 * Semana 7 — refactor del punto de mayor acoplamiento: antes de este cambio,
 * `PatientController` repetía un bloque try/catch con un `match`/`if` de
 * excepción -> código HTTP en cada acción (`store`, `resolveMatchCandidate`).
 * Añadir una nueva excepción de dominio obligaba a tocar el controlador en
 * más de un lugar. Con este mapa centralizado, el controlador solo delega
 * (ver `PatientController::tryAction()`) y agregar una excepción nueva es
 * un cambio de una sola línea aquí, sin abrir el controlador (OCP).
 *
 * No cambia el contrato observable: mismo cuerpo `{"message": "..."}` y
 * mismos códigos HTTP que la versión anterior (ver EVIDENCIA.md semana 7).
 */
final class PatientExceptionResponder
{
    /** @var array<class-string<Throwable>, int> */
    private const STATUS_BY_EXCEPTION = [
        DuplicateDpiException::class => 409,
        MatchCandidateNotFoundException::class => 404,
        LocalPatientNotFoundException::class => 404,
        MatchCandidateAlreadyResolvedException::class => 409,
        InvalidMatchCandidateSelectionException::class => 422,
    ];

    /**
     * Indica si esta clase sabe traducir la excepción dada. El llamador debe
     * relanzar cualquier excepción para la que esto devuelva `false`.
     */
    public static function handles(Throwable $exception): bool
    {
        return array_key_exists($exception::class, self::STATUS_BY_EXCEPTION);
    }

    public static function respond(Throwable $exception): JsonResponse
    {
        $status = self::STATUS_BY_EXCEPTION[$exception::class]
            ?? throw new \LogicException(sprintf(
                '%s no sabe traducir %s; llama primero a handles().',
                self::class,
                $exception::class,
            ));

        return response()->json(['message' => $exception->getMessage()], $status);
    }
}
