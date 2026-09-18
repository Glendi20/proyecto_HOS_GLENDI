<?php

namespace Tests\Unit\Http\Support\Patients;

use App\Domain\Patients\Exceptions\DuplicateDpiException;
use App\Domain\Patients\Exceptions\InvalidMatchCandidateSelectionException;
use App\Domain\Patients\Exceptions\LocalPatientNotFoundException;
use App\Domain\Patients\Exceptions\MatchCandidateAlreadyResolvedException;
use App\Domain\Patients\Exceptions\MatchCandidateNotFoundException;
use App\Http\Support\Patients\PatientExceptionResponder;
use Exception;
use Tests\TestCase;

/**
 * Semana 7 — cubre el mapa excepción -> HTTP que antes vivía repetido
 * dentro de PatientController (try/catch por acción).
 *
 * Extiende el TestCase de Laravel (no PHPUnit\Framework\TestCase puro)
 * porque PatientExceptionResponder usa el helper `response()`, que
 * requiere el contenedor de la aplicación arrancado.
 */
class PatientExceptionResponderTest extends TestCase
{
    /**
     * @return array<string, array{0: \Throwable, 1: int}>
     */
    public static function knownExceptions(): array
    {
        return [
            'DuplicateDpiException -> 409' => [new DuplicateDpiException('dpi duplicado'), 409],
            'MatchCandidateNotFoundException -> 404' => [new MatchCandidateNotFoundException('no encontrado'), 404],
            'LocalPatientNotFoundException -> 404' => [new LocalPatientNotFoundException('paciente no encontrado'), 404],
            'MatchCandidateAlreadyResolvedException -> 409' => [new MatchCandidateAlreadyResolvedException('ya resuelto'), 409],
            'InvalidMatchCandidateSelectionException -> 422' => [new InvalidMatchCandidateSelectionException('seleccion invalida'), 422],
        ];
    }

    /** @dataProvider knownExceptions */
    public function test_traduce_cada_excepcion_de_dominio_a_su_codigo_http(\Throwable $exception, int $expectedStatus): void
    {
        $this->assertTrue(PatientExceptionResponder::handles($exception));

        $response = PatientExceptionResponder::respond($exception);

        $this->assertSame($expectedStatus, $response->getStatusCode());
        $this->assertSame(['message' => $exception->getMessage()], $response->getData(true));
    }

    public function test_no_declara_saber_manejar_una_excepcion_ajena_al_modulo(): void
    {
        $this->assertFalse(PatientExceptionResponder::handles(new Exception('cualquier otro error')));
    }
}
