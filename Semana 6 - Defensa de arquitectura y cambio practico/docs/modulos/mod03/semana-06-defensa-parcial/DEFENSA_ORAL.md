# Banco de preguntas y respuestas — Defensa oral (Primer parcial)

**Módulo:** 03 — Paciente local, búsqueda y vínculo con MPI CENTRAL
**Estudiante:** Glendi Patricia Campos Orellana (`Glendi20`)

---

### Pregunta 1: ¿Por qué el registro de un paciente nunca se revierte si CENTRAL falla?

**Respuesta:** Es la regla central del módulo, documentada en `ESPECIFICACION.md` §5 e
implementada en `RegisterLocalPatientUseCase`: el alta local (`patients` en HOSPITAL) se
confirma **antes** de intentar cualquier operación contra CENTRAL. Si `MpiPatientRepository`
lanza `CentralUnavailableException`, el caso de uso la captura, deja el paciente en estado
`pending` y encola un evento en `patient_sync_outbox` con un `event_id` único. La atención
al paciente en el hospital nunca depende de que la red o CENTRAL estén arriba — esto se
verifica en `tests/Feature/Patients/RegisterLocalPatientTest.php::continua_localmente_si_central_no_esta_disponible...`.

---

### Pregunta 2: ¿Cómo se garantiza que nunca se fusionen dos personas distintas por error?

**Respuesta:** `PatientMatchingPolicy` (clase pura de dominio, sin SQL ni HTTP) solo puede
devolver una de tres decisiones: `NO_MATCH`, `AUTO_LINK` o `MANUAL_REVIEW`. El auto-vínculo
exige un candidato con score ≥ 0.92 **y** un margen de al menos 0.05 sobre el segundo mejor
candidato (`DOMINANCE_MARGIN`); cualquier otro caso con evidencia queda como
`identity_match_candidate` pendiente de revisión humana. No existe una cuarta rama de código
que fusione automáticamente — la política es exhaustiva por diseño (`match` sin `default`
implícito de fusión).

---

### Pregunta 3: ¿Por qué Repository por caso de uso y no un CRUD genérico?

**Respuesta:** Un `Repository<Patient>` genérico (`find`, `save`, `delete`) filtraría mal las
necesidades reales: `RegisterLocalPatientUseCase` necesita `nextCode()` y `existsByDpi()`,
`SyncPendingPatientsUseCase` necesita `pendingBatch()` y `markSynced()`. Diseñar el puerto
alrededor del caso de uso (Interface Segregation) evita que una implementación (Eloquent o
`InMemory`) tenga que simular operaciones que nunca usa, y hace explícito en la firma del
puerto qué necesita cada flujo de negocio.

---

### Pregunta 4: ¿Qué gana el hospital con el patrón Repository y la doble implementación (Eloquent / InMemory)?

**Respuesta:** El dominio y la aplicación solo conocen la interfaz (`LocalPatientRepository`,
`MpiPatientRepository`, etc.), nunca Eloquent directamente (Inversión de Dependencias, DIP).
Esto permite:
1. Ejecutar `tests/Feature/Patients/*` con `InMemory*Repository`, controlando cada rama de
   `PatientMatchingPolicy` sin una base de datos real.
2. Sustituir la conexión CENTRAL en el futuro (otro motor, otro esquema) sin tocar
   `RegisterLocalPatientUseCase` ni `PatientMatchingPolicy`.

---

### Pregunta 5: ¿Por qué CENTRAL y HOSPITAL son conexiones de base de datos y no un discriminador (`tenant_id`) más?

**Respuesta:** Un discriminador en la misma base solo simula la separación; una FK física
entre `patients` (HOSPITAL) y `mpi_patients` (CENTRAL) sería imposible en producción con dos
bases físicas reales, y rompería la premisa de que cada hospital es dueño de su propio dato.
Por eso `patients.uuid`, `patient_hospital_links.local_patient_uuid` e
`identity_match_candidates.local_patient_uuid` son referencias lógicas (`char(36)`) sin
restricción de FK entre bases — ver Decisión 3 de `ADR-001-arquitectura.md`.

---

### Pregunta 6: Durante la implementación se encontró que `tenancy.database.central_connection` nunca apuntaba realmente a `central`. ¿Por qué importa ese hallazgo?

**Respuesta:** El paquete `stancl/tenancy` nunca tuvo su configuración publicada, así que
`central_connection` caía en su valor por defecto (`env('DB_CONNECTION', 'central')`), es
decir, la **misma** conexión que el resto de la app. En SQLite de un solo archivo esto era
invisible; se volvió un error real (`SQLSTATE[HY000]: no such table: tenants`) en cuanto
`central` pasó a ser una conexión con datos propios y separables. Se corrigió publicando
`config/tenancy.php` y fijando `database.central_connection = central` explícitamente — un
cambio que además dejó en verde una prueba de otro compañero (`Modulo20`) que también usaba
`Tenant::factory()` (ver Decisión 2 de `ADR-001-arquitectura.md`).

---

### Pregunta 7: ¿Cómo respondería este diseño ante un requerimiento nuevo, sin romper lo existente?

**Respuesta:** Ver `CAMBIO_PRACTICO.md`. En resumen: agregar un rol de solo lectura para
auditoría de interoperabilidad no requiere tocar `PatientMatchingPolicy` ni los casos de uso
existentes — solo un caso de uso nuevo (`ListMatchCandidatesUseCase`), un método adicional en
el puerto ya existente (`IdentityMatchCandidateRepository`) y una ruta protegida por el
middleware de roles ya presente en el proyecto (RBAC, módulo #02). La separación en capas
hace que el costo del cambio sea proporcional a lo que realmente cambia, no a todo el módulo.
