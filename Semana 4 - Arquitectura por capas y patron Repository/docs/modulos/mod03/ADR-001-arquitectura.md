# ADR-001 — Arquitectura del módulo 03: Paciente local + vínculo MPI CENTRAL

- **Estado:** Aceptado (para esta entrega vertical mínima)
- **Fecha:** 2026-08-21
- **Autora:** Glendi Patricia Campos Orellana

## Contexto

La línea base obligatoria de la actividad exige una base CENTRAL de control y una base HOSPITAL independiente por sucursal, con referencias entre bases por UUID lógico (nunca FK remota), sincronización por outbox/inbox idempotente, y una regla explícita de continuidad ante fallas de red. El repositorio base, sin embargo, implementa multi-tenancy de **esquema compartido** (columna `tenant_id`) sobre una única base de datos, con una conexión `central` (Stancl Tenancy) usada solo para el modelo `Tenant`.

Se decide **adaptar**, no sustituir, ese patrón: en vez de introducir una base de datos física por sucursal (lo que exigiría reescribir la autenticación, RBAC y todos los módulos ya construidos por otros compañeros — fuera del alcance individual y prohibido por "no cree otro Micro-HIS"), se refuerza la separación **CENTRAL vs. HOSPITAL** como una separación de **conexión de base de datos**, ya prevista por el proyecto, y se corrige lo mínimo necesario para que esa separación sea real y no solo nominal.

## Decisión 1 — CENTRAL y HOSPITAL son conexiones de base de datos distintas, no solo un discriminador

- **HOSPITAL** = conexión `default` (Laravel `DB_CONNECTION`), con aislamiento adicional por columna `tenant_id` (cada tenant = una sucursal). Aquí vive `patients` (el `local_patient` de esta actividad), `patient_sync_outbox`.
- **CENTRAL** = conexión `central` (`config/database.php`), configurable de forma independiente vía `CENTRAL_DB_*` en `.env`. Aquí viven `tenants` (identidad de red de las sucursales — módulo #01), `mpi_patients`, `patient_hospital_links`, `identity_match_candidates`.
- En desarrollo, si `CENTRAL_DB_*` no se define, `central` cae al mismo SQLite que `default` (compatibilidad con `docs/README-INSTALACION-BACKEND.md`). En producción, **debe** apuntar a una base PostgreSQL distinta (puede ser otro esquema, otra base o incluso otro host).

**Alternativa descartada:** una base de datos física por sucursal (4 bases HOSPITAL reales). Se descarta para esta entrega individual porque exigiría reescribir la resolución de conexión de TODO el proyecto (autenticación, RBAC, y los ~29 módulos restantes), no solo el mío; la separación CENTRAL/HOSPITAL por conexión ya cumple el objetivo pedagógico (propiedad de datos, ausencia de FK remotas, tolerancia a fallas) sin ese costo.

## Decisión 2 — Ajustes mínimos a contratos compartidos (documentados aquí y en el PR)

Al hacer que `central` sea una conexión *genuinamente* independiente (no un alias del mismo archivo), aparecieron dos incompatibilidades preexistentes en el repositorio base que impedían que la separación funcionara en absoluto, no solo para este módulo:

1. **`tenants` no declaraba conexión.** `App\Models\Tenant` extiende `Stancl\Tenancy\...\Tenant`, que siempre resuelve a la conexión `central`. La migración original creaba la tabla en la conexión por defecto; "funcionaba" solo por coincidencia (mismo archivo SQLite). Se corrigió la migración para declarar explícitamente `protected $connection = 'central'`. Ningún módulo depende de en qué conexión física vive `tenants`, solo de que el modelo siga funcionando igual (y sigue igual).
2. **FK física de `users.tenant_id` hacia `tenants.id`.** Con `tenants` en `central` y `users` en `default`, esa FK sería una FK entre bases — imposible en PostgreSQL real y prohibida por esta arquitectura. Se retiró la restricción de FK (se conserva el índice y el `unique(tenant_id, email)`); `User::tenant()` sigue funcionando porque es una relación Eloquent por valor de columna, no por integridad referencial del motor.
3. **Unicidad de `patients.code`.** Era única a nivel global; RF-11 exige un correlativo único **por hospital** (`PAC-0001` en dos hospitales distintos es válido). Se cambió a `unique(tenant_id, code)`.

Estos tres cambios se limitan a migraciones y no alteran comportamiento observable de otros módulos (según `docs/module-03/vista-arquitectonica.md`, ningún módulo depende de estas restricciones específicas, solo de las relaciones `patient_id`/`tenant_id`).

## Decisión 3 — Referencias lógicas, nunca FK remotas

- `patients.uuid` (HOSPITAL) es el único identificador que CENTRAL puede citar. `patient_hospital_links.local_patient_uuid` y `identity_match_candidates.local_patient_uuid` son `char(36)` **sin** restricción de FK hacia `patients` (viven en bases distintas).
- `patient_hospital_links.tenant_id` y `identity_match_candidates.tenant_id` **sí** son FK reales hacia `tenants.id`, porque ambas tablas viven en la misma conexión `central` — no es una FK remota.
- `patients.global_id` es una copia de solo lectura (desnormalizada) del `mpi_patient_id` vigente, para no tener que consultar CENTRAL en cada lectura local.

## Decisión 4 — Repository por caso de uso, no CRUD genérico

Los puertos (`App\Application\Patients\Ports\*`) están diseñados alrededor de lo que cada caso de uso necesita (`findCandidates`, `nextCode`, `createPending`, `enqueue`...), no como un CRUD genérico. Domain define entidades y la regla (`PatientMatchingPolicy`) sin conocer Eloquent, HTTP ni SQL. Infrastructure aporta dos implementaciones por puerto: el adaptador Eloquent/PostgreSQL (producción) y un doble `InMemory` (pruebas de aplicación), ambos cumpliendo el mismo contrato observable.

## Decisión 5 — Política de emparejamiento determinística (no pgvector)

Este módulo no es la variante RAG/CAG (esa es la del compañero Billy Cardona): no requiere embeddings ni pgvector. El emparejamiento MPI usa una heurística determinística y explicable (coincidencia de DPI, fecha de nacimiento y similitud de nombre normalizado — `EloquentMpiPatientRepository::score()`), con umbrales configurables (`PATIENTS_MPI_AUTO_LINK_THRESHOLD`, `PATIENTS_MPI_REVIEW_THRESHOLD`). Se documenta como una limitación conocida: una comparación textual más robusta (trigramas / `pg_trgm`, Levenshtein en SQL) es trabajo futuro si el volumen de pacientes crece; no se justifica pgvector para este tipo de comparación estructurada.

## Decisión 6 — Continuidad ante fallas (outbox)

`patient_sync_outbox` vive en HOSPITAL. `RegisterLocalPatientUseCase` intenta el vínculo MPI de forma síncrona (mejor experiencia cuando CENTRAL está arriba); si `MpiPatientRepository` lanza `CentralUnavailableException` (excepción de Application que envuelve fallos de conexión/consulta de Infrastructure), el alta local ya confirmada se conserva, el paciente queda `pending`, y se encola un evento con `event_id` único. `patients:sync-mpi` (comando idempotente) drena ese outbox después, reintentando sin duplicar vínculos.

## Riesgos conocidos y mitigación

| Riesgo | Mitigación actual | Trabajo futuro |
|---|---|---|
| `users`/`tenants` (módulo #01) aún no evolucionaron a `central_user`/`hospital_membership` en CENTRAL. | Se documenta como alcance de otro módulo; este ADR no lo fuerza. | Módulo #01 puede completar esa migración sin afectar este módulo (usa `tenant_id`/`uuid`, no FKs). |
| Heurística de emparejamiento es simple (no usa búsqueda textual avanzada). | Umbrales configurables + siempre auditable (`identity_match_candidates.reason`, `matched_fields`). | Evaluar `pg_trgm` si el volumen crece (ver sección 13.4 del enunciado, aplicable por analogía). |
| Sin base PostgreSQL real ejecutada en esta entrega (ver `EVIDENCIA.md`). | Migraciones y pruebas están escritas para correr contra PostgreSQL real (`tests/Feature/Patients/PostgresPatientIntegrationTest.php`) usando el PostgreSQL 17 ya instalado en la máquina de desarrollo. | Ejecutar `composer install` + `php artisan migrate` + la prueba de integración antes de la defensa oral. |

## Consecuencias

- Cumple: propiedad CENTRAL/HOSPITAL, UUID lógicos, outbox/inbox con idempotencia, regla de no-fusión automática, Repository orientado al caso de uso, PostgreSQL como motor objetivo.
- Costo: dos migraciones de contrato compartido (`users`, `tenants`) documentadas aquí y en el PR, con impacto mínimo y sin romper otros módulos.
