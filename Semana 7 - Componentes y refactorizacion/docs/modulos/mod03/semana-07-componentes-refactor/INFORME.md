# Informe técnico — Diseño de componentes y refactorización (Semana 7)

**Estudiante:** Glendi Patricia Campos Orellana (`Glendi20`)
**Módulo:** 03 — Paciente local, búsqueda y vínculo con MPI CENTRAL

## 1. Componentes backend

La topología de componentes backend (Presentation / Application / Domain / Infrastructure)
quedó establecida en la semana 4 y se mantiene sin cambios estructurales — ver
[`../diagramas/04-componentes.md`](../diagramas/04-componentes.md). Esta semana se agrega un
componente nuevo dentro de `Presentation`: `PatientExceptionResponder`, que absorbe una
responsabilidad que antes estaba disuelta dentro de `PatientController` (ver
[`COMPONENTES.md`](./COMPONENTES.md)).

## 2. Componentes frontend (propuesta)

El proyecto es una API backend sin cliente propio todavía. Se propone una topología mínima
de componentes de interfaz (`PatientSearchView`, `PatientRegisterForm`,
`MatchCandidateReviewPanel`) sobre una única capa de transporte (`PatientApiClient`) que
concentra las cabeceras de autenticación/tenant y la traducción de errores del contrato —
el mismo principio que motivó el refactor del backend. Detalle en
[`COMPONENTES.md`](./COMPONENTES.md) §2.

## 3. Identificación del punto de mayor acoplamiento

`PatientController::store()` y `::resolveMatchCandidate()` repetían un `try/catch` con el
mapeo manual de 5 excepciones de dominio distintas a sus códigos HTTP. Esto acoplaba el
controlador al catálogo completo de excepciones del módulo y obligaba a tocarlo cada vez que
el catálogo creciera (por ejemplo, ante el cambio práctico de la semana 6).

## 4. Refactor aplicado

Se extrajo el mapeo a `App\Http\Support\Patients\PatientExceptionResponder`
(`handles()` / `respond()`) y el controlador delega en un método privado `tryAction()`. El
detalle línea por línea, la justificación y la evidencia de pruebas están en
[`REFACTOR_ANTES_DESPUES.md`](./REFACTOR_ANTES_DESPUES.md).

## 5. Resultado

| Dimensión | Antes | Después |
|---|---|---|
| Excepciones que el controlador conoce por nombre | 5 | 0 |
| Puntos a tocar para agregar una excepción nueva | El controlador | Una entrada en el mapa del responder |
| Prueba dedicada al mapeo | No existía | `PatientExceptionResponderTest` (6 casos) |
| Contrato HTTP observable | `{"message": ...}` + código | Idéntico — verificado con la suite existente en verde |

## 6. Conclusión

El desacoplamiento logrado en la semana 4 (capas + Repository) hizo que este refactor fuera
acotado y de bajo riesgo: no fue necesario tocar `Domain`, `Application` ni `Infrastructure`, y
ninguna prueba de feature existente necesitó modificarse. El módulo queda mejor preparado
para el cambio práctico propuesto en la semana 6 (rol `AuditorMPI`), que añadiría una nueva
ruta de solo lectura sin volver a acoplar el controlador a excepciones concretas.
