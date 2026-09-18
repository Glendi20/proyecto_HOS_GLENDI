# Refactorización: traducción de excepciones en `PatientController`

## 1. Punto de mayor acoplamiento identificado

`PatientController` (semana 4) exponía tres acciones. Dos de ellas repetían el mismo patrón:
un `try/catch` con una excepción de dominio distinta por `catch`, cada una traducida a mano
a un código HTTP:

```php
// store() — antes
try {
    $result = $this->registerPatient->handle(...);
} catch (DuplicateDpiException $e) {
    return response()->json(['message' => $e->getMessage()], 409);
}

// resolveMatchCandidate() — antes
try {
    $globalId = ...;
} catch (MatchCandidateNotFoundException $e) {
    return response()->json(['message' => $e->getMessage()], 404);
} catch (MatchCandidateAlreadyResolvedException $e) {
    return response()->json(['message' => $e->getMessage()], 409);
} catch (LocalPatientNotFoundException $e) {
    return response()->json(['message' => $e->getMessage()], 404);
} catch (InvalidMatchCandidateSelectionException $e) {
    return response()->json(['message' => $e->getMessage()], 422);
}
```

**Problema:** el controlador conocía, uno por uno, los 5 tipos de excepción de dominio del
módulo y su código HTTP correspondiente. Agregar una excepción nueva (por ejemplo, para el
cambio práctico de la semana 6) obligaría a tocar el controlador — y el mismo mapeo tendría
que repetirse si mañana se agrega una cuarta acción al controlador.

## 2. Diseño del refactor

Se extrajo el mapeo a `App\Http\Support\Patients\PatientExceptionResponder`
(`handles()` + `respond()`), y el controlador ahora delega en un único método privado:

```php
// PatientController — después
public function store(RegisterLocalPatientRequest $request): JsonResponse
{
    $tenant = $this->currentTenant($request);

    return $this->tryAction(function () use ($request, $tenant): JsonResponse {
        $result = $this->registerPatient->handle(...);

        return response()->json([...], 201);
    });
}

private function tryAction(callable $action): JsonResponse
{
    try {
        return $action();
    } catch (Throwable $e) {
        if (PatientExceptionResponder::handles($e)) {
            return PatientExceptionResponder::respond($e);
        }

        throw $e; // nunca oculta un error de programación como regla de negocio
    }
}
```

## 3. Comparación

| Dimensión | Antes | Después |
|---|---|---|
| Excepciones que el controlador conoce por nombre | 5 (`DuplicateDpiException`, `MatchCandidateNotFoundException`, `LocalPatientNotFoundException`, `MatchCandidateAlreadyResolvedException`, `InvalidMatchCandidateSelectionException`) | 0 — solo conoce `Throwable` y delega en `PatientExceptionResponder` |
| Lugares que tocar para agregar una excepción nueva | El controlador (en cada acción que la necesite) | Una sola línea en `PatientExceptionResponder::STATUS_BY_EXCEPTION` |
| Cobertura de pruebas del mapeo excepción → HTTP | Implícita, solo a través de pruebas de feature (`RegisterLocalPatientTest`, `ReviewMatchCandidateTest`) | Explícita y aislada: `PatientExceptionResponderTest` (5 casos + 1 caso negativo) |
| Principio reforzado | — | **OCP** (abierto a extender el mapa, cerrado a modificar el controlador) y **SRP** (el controlador ya no decide códigos HTTP por excepción) |

## 4. Verificación de que el contrato externo no cambió

El refactor es **interno**: la forma del JSON de error (`{"message": "..."}`) y los códigos
HTTP (404/409/422) son idénticos a los de antes — así lo exige `docs/module-03/contrato-api.md`
(errores transversales), que no se modifica en esta entrega.

Evidencia de ejecución real, `php artisan test --filter=Patients` tras el refactor:

```
PASS  Tests\Unit\Domain\Patients\DpiTest
PASS  Tests\Unit\Domain\Patients\LocalPatientTest
PASS  Tests\Unit\Domain\Patients\PatientDemographicsTest
PASS  Tests\Unit\Domain\Patients\PatientMatchingPolicyTest
PASS  Tests\Unit\Http\Support\Patients\PatientExceptionResponderTest   ← nueva
WARN  Tests\Feature\Patients\PostgresPatientIntegrationTest (4 omitidas, sin pgsql real)
PASS  Tests\Feature\Patients\RegisterLocalPatientTest
PASS  Tests\Feature\Patients\ReviewMatchCandidateTest
PASS  Tests\Feature\Patients\SearchLocalPatientsTest

Tests: 4 skipped, 36 passed (87 assertions)
```

Las mismas aserciones de `RegisterLocalPatientTest::rechaza_dpi_duplicado_dentro_del_mismo_tenant`
(`assertStatus(409)`) y de `ReviewMatchCandidateTest` (`assertStatus(409)`, `assertStatus(422)`)
pasan sin modificación alguna en su código — la prueba de que el comportamiento observable no
cambió es que **no fue necesario tocar ni un solo `assert` de las pruebas ya existentes**.
