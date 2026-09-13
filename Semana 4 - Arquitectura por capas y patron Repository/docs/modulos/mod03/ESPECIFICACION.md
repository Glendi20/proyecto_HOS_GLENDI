# Módulo 03 — Paciente local, búsqueda y vínculo con MPI CENTRAL

- **Estudiante:** Glendi Patricia Campos Orellana
- **GitHub:** `Glendi20`
- **Rama:** `feature/asii-03-pacientes-glendi20`
- **Actividad:** Actividad integradora de arquitectura federada y persistencia (PostgreSQL, CENTRAL/HOSPITAL, continuidad del proyecto final)

Este documento **evoluciona** el análisis de las semanas 1–3 (`docs/module-03/*.md`) hacia la línea base arquitectónica obligatoria de esta actividad: CENTRAL/HOSPITAL, PostgreSQL, UUID lógicos entre bases y outbox/inbox. No lo reemplaza: la narrativa de actores y el principio SOLID de esas semanas se mantienen vigentes y se referencian donde aplica.

## 1. Problema

Cada sucursal hospitalaria (HOSPITAL) necesita registrar y buscar sus propios pacientes sin depender de que la red o CENTRAL estén disponibles — la atención no puede detenerse porque un enlace de red falle. Al mismo tiempo, el sistema de red (CENTRAL) necesita poder reconocer cuándo dos registros de dos hospitales distintos (o del mismo) corresponden a la **misma persona real**, para eventualmente coordinar continuidad de atención, sin que eso implique mover, copiar ni fusionar el expediente clínico fuera del hospital donde vive.

Esto exige separar dos responsabilidades que hoy, en la línea base del repositorio (semanas 1–3), estaban mezcladas en una sola tabla `patients` de alcance únicamente local:

1. **Identidad local (HOSPITAL):** el paciente tal como existe en la sucursal — su autoridad clínica.
2. **Identidad de red (CENTRAL):** el reconocimiento de que ese paciente local *podría ser* la misma persona que ya existe en el Master Patient Index (MPI) de la red, con un nivel de confianza explícito y auditable.

## 2. Actores

Ver también [`docs/module-03/actores-casos-de-uso.md`](../../module-03/actores-casos-de-uso.md) (semana 1), vigente para CU-01..CU-04. Esta actividad agrega:

| Actor | Tipo | Descripción |
|---|---|---|
| **Recepcionista / Admin** | Principal | Registra pacientes locales y resuelve manualmente coincidencias MPI ambiguas. |
| **Médico / Enfermera** | Secundario | Busca pacientes locales (solo lectura); no interviene en el vínculo MPI. |
| **CENTRAL (MPI)** | Sistema colaborador | Base de red que mantiene el índice maestro de pacientes (`mpi_patients`) y los vínculos hospital↔identidad (`patient_hospital_link`). Puede no estar disponible. |
| **Sistema (outbox sync)** | Soporte | Job/comando (`patients:sync-mpi`) que reintenta de forma idempotente el vínculo MPI de los pacientes que quedaron pendientes por caída de CENTRAL. |

## 3. Historias de usuario

**HU-01.** Como Recepcionista, quiero registrar un paciente en mi hospital aunque la red o CENTRAL estén caídos, para no detener la atención.

**HU-02.** Como Recepcionista, quiero que el sistema intente vincular automáticamente al paciente con su identidad de red cuando la coincidencia sea clara, para no duplicar trabajo de verificación manual.

**HU-03.** Como Recepcionista/Admin, quiero que una coincidencia ambigua (posible pero no segura) quede marcada para revisión humana, para nunca fusionar por error el expediente de dos personas distintas.

**HU-04.** Como Recepcionista, Admin, Médico o Enfermera, quiero buscar pacientes de mi propio hospital por nombre, DPI o código, para atenderlos.

## 4. Alcance

### En alcance (flujo vertical mínimo)

- Registro de paciente local (HOSPITAL) con datos demográficos mínimos + DPI.
- Búsqueda de pacientes locales por nombre, DPI o código, acotada al tenant (hospital) autenticado.
- Intento de vínculo automático con el MPI de CENTRAL al registrar (`auto_link` / `manual_review` / identidad nueva).
- Continuidad local si CENTRAL no responde (outbox + `mpi_link_status = pending`).
- Sincronización idempotente posterior de los pendientes (`patients:sync-mpi`).
- Revisión humana explícita de una coincidencia ambigua (`identity_match_candidate`): confirmar o rechazar.

### Fuera de alcance de esta actividad (documentado como no-alcance, no como omisión)

- Edición de datos de contacto/seguro del paciente (RF-04 / CU-02 de la semana 2) — queda para un incremento posterior; el foco de esta actividad es el vínculo federado, no el CRUD administrativo completo.
- Membresía, autenticación y RBAC completos (módulos #01 y #02): se consumen tal como existen hoy.
- Admisión, expediente clínico, alergias (módulos #07, #10, #12): solo se referencian por `patient_id`/`uuid`, no se implementan aquí.
- Fusión automática de identidades: **nunca** ocurre, por regla central (sección 5).
- pgvector / embeddings: no aplica a este módulo (es exclusivo de la variante 04, RAG/CAG).

## 5. Regla central obligatoria

> El registro local continúa si CENTRAL o la red fallan. Una coincidencia MPI ambigua crea `identity_match_candidate` y nunca fusiona silenciosamente ni mueve el expediente fuera del hospital fuente.

Esta regla se traduce en tres invariantes verificables (ver `PatientMatchingPolicy` y `RegisterLocalPatientUseCase`):

1. El alta local (`patients` en HOSPITAL) se confirma **antes** de intentar cualquier operación contra CENTRAL, y nunca se revierte por una falla de CENTRAL.
2. La decisión de vínculo MPI es siempre una de exactamente tres: `no_match` (identidad nueva), `auto_link` (candidato único dominante) o `manual_review` (ambiguo) — nunca una fusión implícita.
3. Toda comunicación con CENTRAL es idempotente (outbox con `event_id` único) y tolerante a fallas (se reintenta, nunca se pierde el hecho de que el paciente ya existe localmente).

## 6. Requerimientos funcionales (RF) de esta actividad

Complementan RF-01 a RF-10 de [`docs/module-03/rf-rnf-solid.md`](../../module-03/rf-rnf-solid.md) (semana 2, vigentes para el CRUD base).

| # | Requerimiento | Caso de uso |
|---|---|---|
| RF-11 | El sistema debe registrar un paciente local con datos demográficos mínimos y DPI opcional, generando un `uuid` lógico único y un código correlativo `PAC-0001` **por hospital** (tenant). | CU-05 |
| RF-12 | El sistema debe intentar vincular el paciente local con una identidad del MPI de CENTRAL inmediatamente después del alta, sin bloquear ni revertir el alta si CENTRAL falla. | CU-05 |
| RF-13 | Ante un candidato MPI único y dominante (score ≥ umbral de auto-vínculo y con margen suficiente sobre el segundo candidato), el sistema debe vincular automáticamente (`auto_linked`) y asignar `global_id`. | CU-05 |
| RF-14 | Ante cero candidatos con evidencia suficiente, el sistema debe crear una identidad nueva en el MPI y vincularla automáticamente. | CU-05 |
| RF-15 | Ante una coincidencia ambigua (más de un candidato con evidencia suficiente, o score intermedio), el sistema debe crear una `identity_match_candidate` pendiente y dejar el paciente en `manual_review`, sin asignar `global_id`. | CU-05 |
| RF-16 | Si CENTRAL no responde durante el alta, el sistema debe conservar el paciente local, marcarlo `pending` y encolar un evento de sincronización idempotente (outbox) para reintentar después. | CU-05 |
| RF-17 | El sistema debe permitir a un rol autorizado confirmar (eligiendo un candidato) o rechazar (creando identidad nueva) una `identity_match_candidate` pendiente; una vez resuelta, no puede reprocesarse. | CU-06 |
| RF-18 | El sistema debe permitir buscar pacientes locales por nombre, DPI o código, acotado estrictamente al tenant del usuario autenticado. | CU-03 (vigente) |
| RF-19 | El sistema debe rechazar el registro de un paciente con un DPI ya usado por otro paciente del mismo hospital. | CU-05 |

## 7. Requerimientos no funcionales (RNF)

Complementan RNF-01 a RNF-07 de la semana 2.

| # | Requerimiento | Categoría |
|---|---|---|
| RNF-08 | Ninguna referencia entre la base HOSPITAL y la base CENTRAL puede ser una clave foránea física; solo UUID lógicos (`uuid`, `global_id`, `local_patient_uuid`). | Arquitectura / Integridad federada |
| RNF-09 | Todo evento de sincronización HOSPITAL→CENTRAL debe ser idempotente (reintentar el mismo `event_id` no debe duplicar efectos). | Confiabilidad |
| RNF-10 | El vínculo MPI y la revisión de coincidencias deben quedar auditables (quién decidió, cuándo, con qué candidatos). | Auditoría |
| RNF-11 | Un fallo de CENTRAL no debe producir un código de error al usuario final: el alta local se confirma igual (HTTP 201) con `central_available: false`. | Disponibilidad / Continuidad |

## 8. Criterios de aceptación (Gherkin resumido)

### CU-05 — Registrar paciente local + vínculo MPI

- **Dado** CENTRAL disponible y sin candidatos MPI con evidencia suficiente, **cuando** se registra un paciente, **entonces** se crea una identidad nueva en el MPI y el paciente queda `auto_linked` con `global_id`.
- **Dado** CENTRAL disponible y un único candidato dominante, **cuando** se registra, **entonces** el paciente queda `auto_linked` con el `global_id` de ese candidato.
- **Dado** CENTRAL disponible y una coincidencia ambigua, **cuando** se registra, **entonces** el paciente queda `manual_review`, sin `global_id`, y existe una `identity_match_candidate` pendiente.
- **Dado** CENTRAL no disponible, **cuando** se registra, **entonces** el alta local se confirma (201), el paciente queda `pending`, y existe un evento en el outbox.
- **Dado** un DPI ya usado en el mismo hospital, **cuando** se intenta registrar, **entonces** el sistema responde 409 y no crea el registro.

### CU-06 — Revisar coincidencia MPI ambigua

- **Dado** una `identity_match_candidate` pendiente, **cuando** un Recepcionista/Admin confirma uno de los candidatos, **entonces** el paciente queda `auto_linked` con ese `global_id` y la coincidencia queda `confirmed`.
- **Dado** la misma coincidencia, **cuando** se intenta resolver una segunda vez, **entonces** el sistema responde 409 (ya resuelta, inmutable).
- **Dado** un `mpi_patient_id` que no es uno de los candidatos originales, **cuando** se intenta confirmar, **entonces** el sistema responde 422.

### CU-03 — Buscar pacientes (vigente, semana 2)

- Ver [`docs/module-03/rf-rnf-solid.md`](../../module-03/rf-rnf-solid.md).

## 9. Contratos que este módulo consume/publica

| Contrato | Dirección | Descripción |
|---|---|---|
| `POST /api/v1/patients` | Publica | Alta de paciente local + intento de vínculo MPI. |
| `GET /api/v1/patients` | Publica | Búsqueda paginada de pacientes del tenant activo. |
| `POST /api/v1/patients/match-candidates/{id}/resolve` | Publica | Confirma/rechaza una coincidencia MPI ambigua. |
| `php artisan patients:sync-mpi` | Publica (interno) | Reintenta vínculos MPI pendientes (outbox). |
| Middleware `tenant` + `jwt.auth` | Consume | Identidad y aislamiento por hospital (módulos #01/#02). |
| `patients.id` / `patients.uuid` | Publica | Referencia lógica que consumen módulos #07 (Admisión), #10 (EMR), #12 (Alergias). |

## 10. Trazabilidad con el código

| Elemento de la especificación | Archivo |
|---|---|
| Regla central (política de emparejamiento) | [`app/Domain/Patients/Mpi/PatientMatchingPolicy.php`](../../../app/Domain/Patients/Mpi/PatientMatchingPolicy.php) |
| CU-05 | [`app/Application/Patients/UseCases/RegisterLocalPatientUseCase.php`](../../../app/Application/Patients/UseCases/RegisterLocalPatientUseCase.php) |
| CU-06 | [`app/Application/Patients/UseCases/ReviewMatchCandidateUseCase.php`](../../../app/Application/Patients/UseCases/ReviewMatchCandidateUseCase.php) |
| Sincronización outbox | [`app/Application/Patients/UseCases/SyncPendingPatientsUseCase.php`](../../../app/Application/Patients/UseCases/SyncPendingPatientsUseCase.php) |
| Presentación | [`app/Http/Controllers/Api/V1/PatientController.php`](../../../app/Http/Controllers/Api/V1/PatientController.php) |
| Pruebas de dominio | [`tests/Unit/Domain/Patients/`](../../../tests/Unit/Domain/Patients/) |
| Pruebas de aplicación/feature | [`tests/Feature/Patients/`](../../../tests/Feature/Patients/) |
